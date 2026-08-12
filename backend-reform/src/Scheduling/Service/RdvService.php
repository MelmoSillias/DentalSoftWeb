<?php

namespace App\Scheduling\Service;

use App\Communication\Infrastructure\Persistence\Doctrine\Entity\SmsQueue;
use App\Communication\Infrastructure\Persistence\Doctrine\Repository\SmsQueueRepository;
use App\Communication\Service\SmsService;
use App\CareDelivery\Domain\Model\Consultation as DomainConsultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Service\ConsultationNotificationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\RdvRepository;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use App\Settings\Service\GlobalSettingsService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RdvService
{
    private function startOfDay(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
    }

    private function endOfDay(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(23, 59, 59);
    }

    public function __construct(
        private EntityManagerInterface $em,
        private RdvRepository $rdvRepo,
        private EmployeRepository $employeRepo,
        private ConsultationNotificationService $consultationNotificationService,
        private RdvNotificationService $rdvNotificationService,
        private SmsQueueRepository $smsQueueRepository,
        private SmsService $smsService,
        private GlobalSettingsService $globalSettingsService,
    ) {
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function buildAppointmentReminderDates(DateTimeImmutable $rdvAt, int $daysBefore, string $recurrence): array
    {
        $daysBefore = max(0, $daysBefore);
        $firstSendAt = $rdvAt->modify(sprintf('-%d days', $daysBefore));
        if (!$firstSendAt instanceof DateTimeImmutable) {
            return [];
        }

        if ($recurrence === 'none') {
            return [$firstSendAt];
        }

        $step = match ($recurrence) {
            'daily' => '+1 day',
            'every_2_days' => '+2 days',
            'weekly' => '+1 week',
            default => null,
        };

        if ($step === null) {
            return [$firstSendAt];
        }

        $dates = [];
        $cursor = $firstSendAt;
        $maxOccurrences = 14;

        while ($cursor < $rdvAt && count($dates) < $maxOccurrences) {
            $dates[] = $cursor;
            $cursor = $cursor->modify($step);
        }

        return $dates;
    }

    /**
     * @param array<string, mixed> $smsReminder
     */
    private function queueAppointmentRemindersForRdv(Rdv $rdv, array $smsReminder, string $cabinetName = ''): int
    {
        $enabled = ($smsReminder['enabled'] ?? true) !== false;
        if (!$enabled) {
            return 0;
        }

        $patient = $rdv->getPatient();
        $rdvAt = $rdv->getDateRdv();
        if (!$patient instanceof Patient || !$rdvAt instanceof DateTime) {
            return 0;
        }

        $daysBefore = max(0, (int) ($smsReminder['daysBefore'] ?? 1));
        $recurrence = (string) ($smsReminder['recurrence'] ?? 'none');
        $dates = $this->buildAppointmentReminderDates(DateTimeImmutable::createFromMutable($rdvAt), $daysBefore, $recurrence);
        $now = new DateTimeImmutable();

        $effectiveCabinetName = trim($cabinetName);
        if ($effectiveCabinetName === '') {
            $effectiveCabinetName = $this->globalSettingsService->resolveCabinetName();
        }

        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'date' => $rdvAt->format('d/m/Y'),
            'time' => $rdvAt->format('H:i'),
            'cabinet_name' => $effectiveCabinetName,
        ];

        $queued = 0;
        foreach ($dates as $index => $sendAt) {
            if ($sendAt <= $now) {
                continue;
            }

            $result = $this->smsService->queueTemplateForPatient(
                $patient,
                'appointment_reminder',
                $variables,
                'appointment-auto',
                $sendAt,
                [
                    'rdvId' => $rdv->getId(),
                    'recurrence' => $recurrence,
                    'daysBefore' => $daysBefore,
                    'occurrenceIndex' => $index + 1,
                    'occurrenceCount' => count($dates),
                ]
            );

            if (($result['success'] ?? false) === true) {
                ++$queued;
            }
        }

        return $queued;
    }

    private function buildAppointmentSmsSummaries(array $rdvs): array
    {
        $patientIds = [];
        foreach ($rdvs as $rdv) {
            if (!$rdv instanceof Rdv || !$rdv->getPatient()?->getId()) {
                continue;
            }
            $patientIds[] = (int) $rdv->getPatient()->getId();
        }

        $items = $this->smsQueueRepository->findAppointmentRemindersForPatients($patientIds);
        $summaries = [];

        foreach ($items as $item) {
            if (!$item instanceof SmsQueue) {
                continue;
            }

            $metadata = $item->getMetadata() ?? [];
            $rdvId = (int) ($metadata['rdvId'] ?? 0);
            if ($rdvId <= 0 || isset($summaries[$rdvId])) {
                continue;
            }

            $status = $item->getStatus();
            $sendAt = $item->getSendAt();
            $sentAt = $item->getSentAt();
            $isScheduled = $status === SmsQueue::STATUS_PENDING && $sendAt instanceof \DateTimeImmutable && $sendAt > new \DateTimeImmutable();

            $label = match (true) {
                $isScheduled => 'Programmé',
                $status === SmsQueue::STATUS_SENT => 'Envoyé',
                $status === SmsQueue::STATUS_SENDING => 'Envoi en cours',
                $status === SmsQueue::STATUS_FAILED => 'Non envoyé',
                default => 'En attente',
            };

            $summaries[$rdvId] = [
                'queueId' => $item->getId(),
                'status' => $status,
                'label' => $label,
                'source' => $item->getSource(),
                'isAutomatic' => $item->getSource() === 'appointment-auto',
                'sendAt' => $sendAt?->format('Y-m-d H:i:s'),
                'sentAt' => $sentAt?->format('Y-m-d H:i:s'),
                'lastError' => $item->getLastError(),
                'message' => $item->getMessage(),
            ];
        }

        return $summaries;
    }

    private function mapRdv(Rdv $rdv, ?array $smsReminder = null): array
    {
        $patient = $rdv->getPatient();

        return [
            'id' => $rdv->getId(),
            'patient' => $patient->getNom() . ' ' . $patient->getPrenom(),
            'patientName' => $patient->getFullName(),
            'patientPhoto' => $patient->getPhoto(),
            'patientData' => [
                'id' => $patient->getId(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'fullname' => $patient->getFullName(),
                'photo' => $patient->getPhoto(),
                'telephone' => $patient->getTelephone(),
            ],
            'medecin' => $rdv->getMedecin()->getNom() . ' ' . $rdv->getMedecin()->getPrenom(),
            'medecin_id' => $rdv->getMedecin()->getId(),
            'description' => $rdv->getDescription(),
            'statut' => $rdv->getStatut(),
            'dateRdv' => $rdv->getDateRdv()->format('Y-m-d H:i:s'),
            'endDate' => $rdv->getEndDate()?->format('Y-m-d H:i:s'),
            'dateCreation' => $rdv->getDateCreation()->format('d-m-Y H:i:s'),
            'reportedAt' => $rdv->getReportedAt() ? 'Reporté au ' . $rdv->getReportedAt()->format('d-m-Y H:i:s') : null,
            'smsReminder' => $smsReminder,
        ];
    }

    /**
     * Scheduling entry (may remap medecin when actor is ROLE_MEDECIN).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRdv(array $data, ?User $actor = null): array
    {
        $payload = [
            'patient_id' => $data['patient'] ?? $data['patient_id'] ?? null,
            'medecin_id' => $data['medecin'] ?? $data['medecin_id'] ?? null,
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'description' => $data['description'] ?? '',
            'duration' => $data['duration'] ?? 30,
        ];

        if (isset($data['smsReminder']) && is_array($data['smsReminder'])) {
            $payload['smsReminder'] = $data['smsReminder'];
        }
        if (isset($data['cabinet_name'])) {
            $payload['cabinet_name'] = $data['cabinet_name'];
        }

        if ($this->isMedecinUser($actor)) {
            $medecin = $this->getMedecinForUser($actor);
            if (!$medecin) {
                return ['error' => 'Aucun médecin associé', 'status' => 403];
            }
            $payload['medecin_id'] = $medecin->getId();
        }

        return $this->createRdvFromPatientPayload($payload, $actor);
    }

    /**
     * Canonical patient_id/medecin_id/date/time payload (Patient ScheduleAppointmentPort).
     * Does not apply ROLE_MEDECIN medecin override — caller owns that.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRdvFromPatientPayload(array $data, ?User $actor = null): array
    {
        if (!isset($data['patient_id'], $data['medecin_id'], $data['date'], $data['time'])) {
            return ['error' => 'Missing required fields', 'status' => 400];
        }

        $patient = $this->em->find(Patient::class, (int) $data['patient_id']);
        if (!$patient instanceof Patient || $patient->isDeleted()) {
            return ['error' => 'Patient not found', 'status' => 404];
        }

        $medecin = $this->employeRepo->find($data['medecin_id']);
        if (!$medecin) {
            return ['error' => 'Medecin not found', 'status' => 404];
        }

        try {
            $rdv = new Rdv();
            $rdv->setPatient($patient)
                ->setMedecin($medecin)
                ->setDescription($data['description'] ?? '')
                ->setStatut(0)
                ->setDuration($data['duration'] ?? 30)
                ->setDateCreation(new DateTime())
                ->setDateRdv(new DateTime($data['date'] . ' ' . $data['time']));

            $this->em->persist($rdv);
            $this->em->flush();

            $this->rdvNotificationService->notifyCreation($rdv, $actor);

            $smsQueuedCount = 0;
            if (isset($data['smsReminder']) && is_array($data['smsReminder'])) {
                $smsQueuedCount = $this->queueAppointmentRemindersForRdv(
                    $rdv,
                    $data['smsReminder'],
                    (string) ($data['cabinet_name'] ?? '')
                );
            }

            return ['success' => true, 'status' => 201, 'rdv_id' => $rdv->getId(), 'smsQueuedCount' => $smsQueuedCount];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }

    public function getStatsForRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->em->createQuery("
            SELECT r.statut, COUNT(r.id) as total
            FROM App\\Scheduling\\Entity\\Rdv r
            WHERE r.dateRdv BETWEEN :start AND :end
            GROUP BY r.statut
        ")
            ->setParameter('start', $this->startOfDay($start))
            ->setParameter('end', $this->endOfDay($end));

        $results = $qb->getResult();

        $stats = [
            'pending' => 0,
            'validated' => 0,
            'postponed' => 0,
            'cancelled' => 0,
        ];

        foreach ($results as $row) {
            switch ((int) $row['statut']) {
                case 0:
                    $stats['pending'] = (int) $row['total'];
                    break;
                case 1:
                    $stats['validated'] = (int) $row['total'];
                    break;
                case -1:
                    $stats['postponed'] = (int) $row['total'];
                    break;
                case -2:
                    $stats['cancelled'] = (int) $row['total'];
                    break;
            }
        }

        return $stats;
    }

    public function getStatsForDate(\DateTimeInterface $date): array
    {
        $start = $this->startOfDay($date);
        $end = $this->endOfDay($date);

        return $this->getStatsForRange($start, $end);
    }

    public function getStatsForMedecinDate(\DateTimeInterface $date, Employe $medecin): array
    {
        $start = $this->startOfDay($date);
        $end = $this->endOfDay($date);

        $buildCount = function (int $statut) use ($start, $end, $medecin) {
            return (int) $this->rdvRepo->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.statut = :statut')
                ->andWhere('r.medecin = :medecin')
                ->andWhere('r.dateRdv BETWEEN :start AND :end')
                ->setParameter('statut', $statut)
                ->setParameter('medecin', $medecin)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();
        };

        return [
            'pending' => $buildCount(0),
            'validated' => $buildCount(1),
            'postponed' => $buildCount(-1),
            'cancelled' => $buildCount(-2),
        ];
    }

    public function listByDate(\DateTimeInterface $date, ?Employe $medecin = null, bool $excludeCancelled = false): array
    {
        $start = $this->startOfDay($date);
        $end = $this->endOfDay($date);

        return $this->listByRange($start, $end, $medecin?->getId(), $excludeCancelled);
    }

    public function listByRange(\DateTimeInterface $start, \DateTimeInterface $end, ?int $medecinId = null, bool $excludeCancelled = false): array
    {
        $qb = $this->rdvRepo->createQueryBuilder('r')
            ->where('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $this->startOfDay($start))
            ->setParameter('end', $this->endOfDay($end))
            ->orderBy('r.dateRdv', 'ASC');

        if ($excludeCancelled) {
            $qb->andWhere('r.statut != :cancelled')->setParameter('cancelled', -2);
        }

        if ($medecinId) {
            $qb->andWhere('r.medecin = :medecin')->setParameter('medecin', $medecinId);
        }

        $rdvs = $qb->getQuery()->getResult();
        $smsSummaries = $this->buildAppointmentSmsSummaries($rdvs);

        return array_map(
            fn(Rdv $rdv) => $this->mapRdv($rdv, $smsSummaries[$rdv->getId()] ?? null),
            $rdvs
        );
    }

    public function listPendingByRange(\DateTimeInterface $start, \DateTimeInterface $end, ?int $medecinId = null): array
    {
        return $this->listByRange($start, $end, $medecinId, true);
    }

    public function getMedecinForUser(?object $user): ?Employe
    {
        return $user ? $this->employeRepo->findOneBy(['user' => $user]) : null;
    }

    private function isMedecinUser(?User $actor): bool
    {
        return $actor instanceof User && in_array('ROLE_MEDECIN', $actor->getRoles(), true);
    }

    private function shouldCreateConsultation(array $payload = []): bool
    {
        $flag = $payload['create_consultation'] ?? false;

        return $flag === true || $flag === 1 || $flag === '1';
    }

    /**
     * @return array{medecin: Employe}|array{error: string, status: int}
     */
    private function resolveMedecinForConsultation(Rdv $rdv, array $payload): array
    {
        $medecinId = isset($payload['medecin']) ? (int) $payload['medecin'] : null;
        $medecin = $medecinId ? $this->employeRepo->find($medecinId) : $rdv->getMedecin();

        if (!$medecin instanceof Employe) {
            return ['error' => 'Un médecin est requis pour créer la consultation.', 'status' => 400];
        }

        $rdv->setMedecin($medecin);

        return ['medecin' => $medecin];
    }

    private function createConsultationFromRdv(Rdv $rdv, Employe $medecin): Consultation
    {
        // Domain validation; legacy entity still owns persist.
        DomainConsultation::create(
            (int) $rdv->getPatient()->getId(),
            (int) $medecin->getId(),
        );

        $consultation = new Consultation();
        $consultation->setMedecin($medecin);
        $consultation->setPatient($rdv->getPatient());
        $consultation->setCreatedAt(new \DateTime());
        $consultation->setStatut(0);

        $this->em->persist($consultation);

        return $consultation;
    }

    public function updateStatus(int $rdvId, int $newStatus, array $payload = [], ?User $actor = null): array
    {
        $rdv = $this->rdvRepo->find($rdvId);
        if (!$rdv) {
            return ['error' => 'RDV non trouvé', 'status' => 404];
        }

        $rdv->setStatut($newStatus);
        $createdConsultation = null;

        if ($newStatus === 1 && $this->shouldCreateConsultation($payload)) {
            $resolved = $this->resolveMedecinForConsultation($rdv, $payload);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $createdConsultation = $this->createConsultationFromRdv($rdv, $resolved['medecin']);
        }

        $this->em->flush();

        if ($createdConsultation) {
            $this->consultationNotificationService->notifyCreation($createdConsultation, $actor);
        }

        if ($newStatus === 1) {
            $this->rdvNotificationService->notifyValidation($rdv, $actor);
        } elseif ($newStatus === -2) {
            $this->rdvNotificationService->notifyCancellation($rdv, $actor);
        } elseif ($newStatus === -1) {
            $this->rdvNotificationService->notifyReport($rdv, $rdv->getReportedAt(), $actor);
        }

        return ['success' => true];
    }

    public function handleAction(Rdv $rdv, string $action, array $payload, ?User $actor = null): array
    {
        $actorMedecin = $this->isMedecinUser($actor) ? $this->getMedecinForUser($actor) : null;
        if ($this->isMedecinUser($actor)) {
            if (!$actorMedecin) {
                return ['error' => 'Aucun médecin associé', 'status' => 403];
            }
            if (!$rdv->getMedecin() || $rdv->getMedecin()->getId() !== $actorMedecin->getId()) {
                throw new AccessDeniedHttpException('Vous ne pouvez pas modifier ce rendez-vous.');
            }
        }

        $createdConsultation = null;
        $newRdv = null;

        if ($action === 'validate') {
            $rdv->setStatut(1);
            if ($this->shouldCreateConsultation($payload)) {
                $resolved = $this->resolveMedecinForConsultation($rdv, $payload);
                if (isset($resolved['error'])) {
                    return $resolved;
                }
                $createdConsultation = $this->createConsultationFromRdv($rdv, $resolved['medecin']);
            }
        } elseif ($action === 'cancel') {
            $rdv->setStatut(-2);
        } elseif ($action === 'report') {
            $newDate = $payload['new_date'] ?? null;
            $newTime = $payload['new_time'] ?? null;
            $newDuration = $payload['new_duration'] ?? null;
            $newMedecinId = $payload['new_medecin'] ?? null;

            if (!$newDate || !$newTime || !$newDuration) {
                return ['error' => 'Paramètres manquants', 'status' => 400];
            }

            $emp = $newMedecinId ? $this->employeRepo->find((int) $newMedecinId) : $rdv->getMedecin();
            if ($actorMedecin) {
                $emp = $actorMedecin;
            }

            $rdv->setStatut(-1);
            $rdv->setReportedAt(new \DateTimeImmutable($newDate . ' ' . $newTime));

            $newRdv = new Rdv();
            $newRdv->setPatient($rdv->getPatient())
                ->setSalle($rdv->getSalle())
                ->setMedecin($emp)
                ->setDuration((int) $newDuration)
                ->setDescription($rdv->getDescription())
                ->setStatut(0)
                ->setDateCreation(new \DateTime())
                ->setDateRdv(new \DateTime($newDate . ' ' . $newTime));

            $this->em->persist($newRdv);
        } else {
            return ['error' => 'Action inconnue', 'status' => 400];
        }

        $this->em->persist($rdv);
        $this->em->flush();

        if ($createdConsultation) {
            $this->consultationNotificationService->notifyCreation($createdConsultation, $actor);
        }

        switch ($action) {
            case 'validate':
                $this->rdvNotificationService->notifyValidation($rdv, $actor);
                break;
            case 'cancel':
                $this->rdvNotificationService->notifyCancellation($rdv, $actor);
                break;
            case 'report':
                $this->rdvNotificationService->notifyReport($rdv, $rdv->getReportedAt(), $actor);
                if ($newRdv) {
                    $this->rdvNotificationService->notifyCreation($newRdv, $actor, true);
                }
                break;
        }

        return ['success' => true];
    }
}
