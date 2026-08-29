<?php

namespace App\CareDelivery\Repository;

use App\CareDelivery\Entity\Consultation;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    public function NewConsultation(array $data, PatientRepository $patientRepo, EmployeRepository $empRepo, bool $flush = true): Consultation
    {
        $entityManager = $this->getEntityManager();
        
        // Récupération des entités
        $medecin = !empty($data['medecin_id']) ? $empRepo->find($data['medecin_id']) : null;
        $patient = $patientRepo->find($data['patient_id']);
        
        if (!$patient) {
            throw new \Exception('Patient introuvable');
        }

        if (!empty($data['medecin_id']) && !$medecin) {
            throw new \Exception('Médecin introuvable');
        }
        // Création de la nouvelle consultation
        $consultation = new Consultation();
        $consultation->setMedecin($medecin);
        $consultation->setPatient($patient);
        $consultation->setType($data['type'] ?? null);

        $createdAt = new \DateTime();
        if (!empty($data['consultation_date']) && !empty($data['consultation_time'])) {
            try {
                $createdAt = new \DateTime($data['consultation_date'] . ' ' . $data['consultation_time']);
            } catch (\Exception) {
                $createdAt = new \DateTime();
            }
        }

        $consultation->setCreatedAt($createdAt); 
        $consultation->setNumeroPassage($this->nextNumeroPassageForDay($createdAt));
        $consultation->setStatut(0); // Statut par défaut

        // Enregistrement de la consultation
        $entityManager->persist($consultation);
        if ($flush) {
            $entityManager->flush();
        }

        return $consultation;
    }

    public function nextNumeroPassageForDay(\DateTimeInterface $day): int
    {
        $start = \DateTimeImmutable::createFromInterface($day)->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromInterface($day)->setTime(23, 59, 59);

        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.numeroPassage)')
            ->where('c.CreatedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    /**
     * @return Consultation[]
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.CreatedAt BETWEEN :from AND :to')
            ->setParameter('from' , $from->format('Y-m-d 00:00:00'))
            ->setParameter('to'   ,$to->format('Y-m-d 23:59:59'))
            ->getQuery()
            ->getResult();
    }


    public function findPendingConsultations(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :pending')
            ->setParameter('pending', 0)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findClosedConsultations(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :closed')
            ->setParameter('closed', 1)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findConsultationsByPatient($patientId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.patient = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('c.CreatedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByPatient(Patient|int $patient): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.ficheMedicale', 'fm')->addSelect('fm')
            ->andWhere('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('c.CreatedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestClosedByPatient(Patient|int $patient): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.ficheMedicale', 'fm')->addSelect('fm')
            ->andWhere('c.patient = :patient')
            ->andWhere('c.statut = :closed')
            ->setParameter('patient', $patient)
            ->setParameter('closed', 1)
            ->orderBy('c.CreatedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findConsultationsByMedecin($medecinId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.medecin = :medecinId')
            ->setParameter('medecinId', $medecinId)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findConsultationsByInfirmier($infirmierId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.infirmier = :infirmierId')
            ->setParameter('infirmierId', $infirmierId)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findConsultationsBySalle($salleId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.salle = :salleId')
            ->setParameter('salleId', $salleId)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findConsultationsByDateRange($startDate, $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.CreatedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingConsultationsByMedecin($medecinId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.medecin = :medecinId')
            ->andWhere('c.statut = :pending')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('pending', 0)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findClosedConsultationsByMedecin($medecinId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.medecin = :medecinId')
            ->andWhere('c.statut = :closed')
            ->setParameter('medecinId', $medecinId)
            ->setParameter('closed', 1)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFullConsultation(int $id): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.medecin', 'm')->addSelect('m')
            ->leftJoin('c.infirmier', 'i')->addSelect('i')
            ->leftJoin('c.salle', 's')->addSelect('s')
            ->leftJoin('c.actes', 'a')->addSelect('a')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Legacy schema: consultation.facture_id may reference devis.id.
     * Clear the link before deleting a devis to avoid FK violations.
     */
    public function clearLegacyDevisReference(int $devisId): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['consultation'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('consultation');
        if (!array_key_exists('facture_id', $columns)) {
            return;
        }

        $connection->executeStatement(
            'UPDATE consultation SET facture_id = NULL WHERE facture_id = :devisId',
            ['devisId' => $devisId]
        );
    }
}
