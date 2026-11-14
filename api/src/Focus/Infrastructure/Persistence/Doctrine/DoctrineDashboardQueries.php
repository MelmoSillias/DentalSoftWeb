<?php

declare(strict_types=1);

namespace App\Focus\Infrastructure\Persistence\Doctrine;

use App\Billing\Domain\Entity\Facture;
use App\Billing\Domain\Entity\Paiement;
use App\Billing\Domain\Entity\Transaction;
use App\CareDelivery\Domain\Entity\ActeMedical;
use App\CareDelivery\Domain\Entity\Consultation;
use App\Focus\Application\Port\DashboardQueriesInterface;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Patient\Domain\Entity\Patient;
use App\Scheduling\Domain\Entity\Rdv;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class DoctrineDashboardQueries implements DashboardQueriesInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function countNewPatients(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(p.id)')->from(Patient::class,'p')
            ->andWhere('p.deletedAt IS NULL')->andWhere('p.dateInscription BETWEEN :from AND :to');
        if ($doctor !== null) $qb->innerJoin('p.consultations','c')->andWhere('c.medecin = :doctor')->setParameter('doctor',$doctor);
        return (int) $this->period($qb,$from,$to)->getQuery()->getSingleScalarResult();
    }

    public function countPatients(?Employe $doctor = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(DISTINCT p.id)')->from(Patient::class,'p')->andWhere('p.deletedAt IS NULL');
        if ($doctor !== null) $qb->innerJoin('p.consultations','c')->andWhere('c.medecin = :doctor')->setParameter('doctor',$doctor);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int
    {
        return (int) $this->consultations($from,$to,$doctor)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
    }

    public function countPaidConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): int
    {
        return (int) $this->consultations($from,$to,$doctor)->select('COUNT(DISTINCT c.id)')
            ->leftJoin('c.paiement','pd')->leftJoin('c.factureAssurance','fa')
            ->andWhere('pd.id IS NOT NULL OR fa.isConsultationPayante = true')->getQuery()->getSingleScalarResult();
    }

    public function countAppointments(\DateTimeImmutable $from, \DateTimeImmutable $to, int $status, ?Employe $doctor = null): int
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(r.id)')->from(Rdv::class,'r')
            ->innerJoin('r.patient','p')->andWhere('p.deletedAt IS NULL')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')->andWhere('r.statut = :status')->setParameter('status',$status);
        $this->doctorScope($qb,'r',$doctor);
        return (int) $this->period($qb,$from,$to)->getQuery()->getSingleScalarResult();
    }

    public function pendingConsultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null, ?int $limit = null): array
    {
        $qb = $this->consultations($from,$to,$doctor)->andWhere('c.statut = 0');
        if ($limit !== null) $qb->leftJoin('c.medecin','m')->addSelect('p','m')->orderBy('c.CreatedAt','ASC')->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }

    public function doctorConsultations(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->consultations($from,$to,$doctor)->getQuery()->getResult();
    }

    public function invoicesForPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Preserve the historical global-card perimeter; patient tabs apply their own filter.
        return $this->period($this->em->createQueryBuilder()->select('f')->from(Facture::class,'f')
            ->andWhere('f.dateFacture BETWEEN :from AND :to'),$from,$to)->getQuery()->getResult();
    }

    public function invoicesForDoctor(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->invoices($from,$to,$doctor)->getQuery()->getResult();
    }

    public function recentInvoices(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): array
    {
        return $this->invoices($from,$to,$doctor)->addSelect('c','p','m')->leftJoin('c.medecin','m')
            ->orderBy('f.dateFacture','DESC')->setMaxResults(10)->getQuery()->getResult();
    }

    public function pendingAppointments(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): array
    {
        $qb = $this->em->createQueryBuilder()->select('r','p','m')->from(Rdv::class,'r')
            ->leftJoin('r.patient','p')->leftJoin('r.medecin','m')->andWhere('r.statut = 0')
            ->andWhere('r.dateRdv BETWEEN :from AND :to')->andWhere('p.deletedAt IS NULL')
            ->orderBy('r.dateRdv','ASC')->setMaxResults(10);
        $this->doctorScope($qb,'r',$doctor);
        return $this->period($qb,$from,$to)->getQuery()->getResult();
    }

    public function recentPayments(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->period($this->em->createQueryBuilder()->select('pd','c','p','fa','fap')->from(Paiement::class,'pd')
            ->leftJoin('pd.consultation','c')->leftJoin('c.patient','p')
            ->leftJoin('pd.factureAssurance','fa')->leftJoin('fa.patient','fap')
            ->andWhere('pd.date BETWEEN :from AND :to')->andWhere('p.deletedAt IS NULL OR fap.deletedAt IS NULL')
            ->orderBy('pd.date','DESC')->setMaxResults(10),$from,$to)->getQuery()->getResult();
    }

    public function doctorPayments(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->period($this->em->createQueryBuilder()->select('pd','c')->from(Paiement::class,'pd')
            ->leftJoin('pd.consultation','c')->leftJoin('c.patient','p')->andWhere('p.deletedAt IS NULL')
            ->andWhere('pd.date BETWEEN :from AND :to')->andWhere('c.medecin = :doctor')->setParameter('doctor',$doctor),$from,$to)
            ->getQuery()->getResult();
    }

    public function transactions(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $type = null): array
    {
        $qb = $this->em->createQueryBuilder()->select('t')->from(Transaction::class,'t')->andWhere('t.dateTransaction BETWEEN :from AND :to');
        if ($type !== null) $qb->leftJoin('t.modeDePaiement','m')->addSelect('m')->andWhere('t.type = :type')->setParameter('type',$type);
        return $this->period($qb,$from,$to)->getQuery()->getResult();
    }

    public function doctorConsultationTotals(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->consultations($from,$to)->select('m.id AS id',"CONCAT(m.nom, ' ', m.prenom) AS name",'COUNT(c.id) AS total',
                'SUM(CASE WHEN pd.id IS NOT NULL OR fa.isConsultationPayante = true THEN 1 ELSE 0 END) AS paid')
            ->join('c.medecin','m')->leftJoin('c.paiement','pd')->leftJoin('c.factureAssurance','fa')
            ->groupBy('m.id')->orderBy('total','DESC')->getQuery()->getArrayResult();
    }

    public function doctorActTotals(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->period($this->em->createQueryBuilder()->select('m.id AS id',"CONCAT(m.nom, ' ', m.prenom) AS name",'COALESCE(SUM(a.quantite), 0) AS acts')
            ->from(ActeMedical::class,'a')->join('a.consultation','c')->join('c.medecin','m')->join('c.patient','p')
            ->andWhere('p.deletedAt IS NULL')->andWhere('c.CreatedAt BETWEEN :from AND :to')->groupBy('m.id')->orderBy('acts','DESC'),$from,$to)
            ->getQuery()->getArrayResult();
    }

    public function topDoctorActs(Employe $doctor, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->period($this->em->createQueryBuilder()->select('a.type AS label','COALESCE(SUM(a.quantite), 0) AS total')->from(ActeMedical::class,'a')
            ->join('a.consultation','c')->join('c.patient','p')->andWhere('p.deletedAt IS NULL')
            ->andWhere('c.medecin = :doctor')->setParameter('doctor',$doctor)->andWhere('c.CreatedAt BETWEEN :from AND :to')
            ->groupBy('a.type')->orderBy('total','DESC')->setMaxResults(6),$from,$to)->getQuery()->getArrayResult();
    }

    private function consultations(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(Consultation::class,'c')->innerJoin('c.patient','p')
            ->andWhere('p.deletedAt IS NULL')->andWhere('c.CreatedAt BETWEEN :from AND :to');
        $this->doctorScope($qb,'c',$doctor);
        return $this->period($qb,$from,$to);
    }

    private function invoices(\DateTimeImmutable $from, \DateTimeImmutable $to, ?Employe $doctor = null): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()->select('f')->from(Facture::class,'f')->leftJoin('f.consultation','c')->leftJoin('c.patient','p')
            ->andWhere('p.deletedAt IS NULL')->andWhere('f.dateFacture BETWEEN :from AND :to');
        $this->doctorScope($qb,'c',$doctor);
        return $this->period($qb,$from,$to);
    }

    private function doctorScope(QueryBuilder $qb, string $alias, ?Employe $doctor): void
    {
        if ($doctor !== null) $qb->andWhere($alias.'.medecin = :doctor')->setParameter('doctor',$doctor);
    }

    private function period(QueryBuilder $qb, \DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $qb->setParameter('from',$from)->setParameter('to',$to);
    }
}
