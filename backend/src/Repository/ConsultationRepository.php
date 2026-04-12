<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    public function NewConsultation(array $data, PatientRepository $patientRepo, EmployeRepository $empRepo): Consultation
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
        $consultation->setStatut(0); // Statut par défaut

        // Enregistrement de la consultation
        $entityManager->persist($consultation);
        $entityManager->flush();

        return $consultation;
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
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByPatient(Patient|int $patient): ?Consultation
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.fiche', 'fo')->addSelect('fo')
            ->leftJoin('c.ficheMedicale', 'fm')->addSelect('fm')
            ->andWhere('c.patient = :patient')
            ->setParameter('patient', $patient)
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
}
