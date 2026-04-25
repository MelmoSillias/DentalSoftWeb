<?php

namespace App\Scheduling\Service;

use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Service\ConsultationNotificationService;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Patient\Service\PatientService;
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
        private PatientService $patientService,
        private ConsultationNotificationService $consultationNotificationService,
        private RdvNotificationService $rdvNotificationService,
    ) {
    }

    private function mapRdv(Rdv $rdv): array
    {
        return [
            'id' => $rdv->getId(),
            'patient' => $rdv->getPatient()->getNom() . ' ' . $rdv->getPatient()->getPrenom(),
            'medecin' => $rdv->getMedecin()->getNom() . ' ' . $rdv->getMedecin()->getPrenom(),
            'medecin_id' => $rdv->getMedecin()->getId(),
            'description' => $rdv->getDescription(),
            'statut' => $rdv->getStatut(),
            'dateRdv' => $rdv->getDateRdv()->format('Y-m-d H:i:s'),
            'endDate' => $rdv->getEndDate()?->format('Y-m-d H:i:s'),
            'dateCreation' => $rdv->getDateCreation()->format('d-m-Y H:i:s'),
            'reportedAt' => $rdv->getReportedAt() ? 'Reporté au ' . $rdv->getReportedAt()->format('d-m-Y H:i:s') : null,
        ];
    }

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

        if ($this->isMedecinUser($actor)) {
            $medecin = $this->getMedecinForUser($actor);
            if (!$medecin) {
                return ['error' => 'Aucun médecin associé', 'status' => 403];
            }
            $payload['medecin_id'] = $medecin->getId();
        }

        return $this->patientService->createRdv($payload, $actor);
    }

    public function getStatsForRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->em->createQuery("
            SELECT r.statut, COUNT(r.id) as total
            FROM App\\Entity\\Rdv r
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

        return array_map(fn(Rdv $rdv) => $this->mapRdv($rdv), $rdvs);
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

    private function canAutoCreateConsultation(?User $actor, array $payload = []): bool
    {
        if (($payload['create_consultation'] ?? null) === false) {
            return false;
        }

        return !$this->isMedecinUser($actor);
    }

    private function createConsultationFromRdv(Rdv $rdv, ?int $medecinId = null): Consultation
    {
        $medecin = $medecinId ? $this->employeRepo->find($medecinId) : $rdv->getMedecin();
        if (!$medecin) {
            throw new NotFoundHttpException('Médecin introuvable');
        }

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

        if ($newStatus === 1 && $this->canAutoCreateConsultation($actor, $payload)) {
            $createdConsultation = $this->createConsultationFromRdv($rdv, $payload['medecin'] ?? null);
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
            if ($this->canAutoCreateConsultation($actor, $payload)) {
                $createdConsultation = $this->createConsultationFromRdv($rdv, $payload['medecin'] ?? null);
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
