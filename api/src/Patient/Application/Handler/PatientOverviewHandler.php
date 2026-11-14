<?php

namespace App\Patient\Application\Handler;

use App\CareDelivery\Domain\Entity\Consultation;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\ConsultationRepository;
use App\Scheduling\Domain\Entity\Rdv;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use App\Patient\Application\Policy\PatientReadScopePolicy;

final class PatientOverviewHandler
{
    public function __construct(
        private PatientRepository $patientRepo,
        private ConsultationRepository $consultationRepo,
        private EntityManagerInterface $em,
        private PatientReadScopePolicy $readScopePolicy,
    ) {
    }

    public function getOverviewStats(?object $user = null, bool $medecinOnly = false): array
    {
        $medecinOnly = $this->readScopePolicy->restrictToMedecin($medecinOnly);

        $medecin = null;
        if ($medecinOnly) {
            $resolved = $this->readScopePolicy->resolveMedecinFromUser($user);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $medecin = $resolved['employe'];
        }

        $todayStart = new DateTimeImmutable('today 00:00:00');
        $todayEnd = new DateTimeImmutable('today 23:59:59');
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $now = new DateTimeImmutable();

        $totalQb = $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deletedAt IS NULL');

        $consultationsTodayQb = $this->consultationRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.patient', 'cp')
            ->andWhere('cp.deletedAt IS NULL')
            ->andWhere('c.CreatedAt BETWEEN :todayStart AND :todayEnd')
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd);

        $upcomingRdvsQb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Rdv::class, 'r')
            ->innerJoin('r.patient', 'rp')
            ->andWhere('rp.deletedAt IS NULL')
            ->andWhere('r.dateRdv >= :now')
            ->andWhere('r.statut IN (0, 1)')
            ->setParameter('now', $now);

        $newPatientsMonthQb = $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.dateInscription >= :monthStart')
            ->setParameter('monthStart', $monthStart);

        $referralsQb = $this->patientRepo->createQueryBuilder('p')
            ->select('p.referencement AS source, COUNT(p.id) AS cnt')
            ->andWhere('p.deletedAt IS NULL')
            ->groupBy('p.referencement')
            ->orderBy('cnt', 'DESC');

        if ($medecin instanceof Employe) {
            $medecinScope = 'EXISTS (SELECT 1 FROM ' . Consultation::class . ' c2 WHERE c2.patient = p AND c2.medecin = :medecin) OR EXISTS (SELECT 1 FROM ' . Rdv::class . ' r2 WHERE r2.patient = p AND r2.medecin = :medecin)';
            $totalQb->andWhere($medecinScope)->setParameter('medecin', $medecin);
            $newPatientsMonthQb->andWhere($medecinScope)->setParameter('medecin', $medecin);
            $referralsQb->andWhere($medecinScope)->setParameter('medecin', $medecin);

            $consultationsTodayQb
                ->andWhere('c.medecin = :medecin')
                ->setParameter('medecin', $medecin);

            $upcomingRdvsQb
                ->andWhere('r.medecin = :medecin')
                ->setParameter('medecin', $medecin);
        }

        $referralRows = $referralsQb->getQuery()->getArrayResult();
        $referrals = array_map(static fn (array $row): array => [
            'source' => ($row['source'] ?? '') !== '' ? (string) $row['source'] : 'Non renseigné',
            'count' => (int) ($row['cnt'] ?? 0),
        ], $referralRows);

        return [
            'totalPatients' => (int) $totalQb->getQuery()->getSingleScalarResult(),
            'consultationsToday' => (int) $consultationsTodayQb->getQuery()->getSingleScalarResult(),
            'upcomingAppointments' => (int) $upcomingRdvsQb->getQuery()->getSingleScalarResult(),
            'newPatientsThisMonth' => (int) $newPatientsMonthQb->getQuery()->getSingleScalarResult(),
            'referrals' => $referrals,
        ];
    }
}
