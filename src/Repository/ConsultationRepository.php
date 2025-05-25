<?php

namespace App\Repository;

use App\Entity\Consultation;
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
        $medecin = $empRepo->find($data['medecin_id']);
        $patient = $patientRepo->find($data['patient_id']);
        
        if (!$medecin || !$patient) {
            throw new \Exception('Médecin ou patient introuvable');
        }
        // Création de la nouvelle consultation
        $consultation = new Consultation();
        $consultation->setMedecin($medecin);
        $consultation->setPatient($patient);
        $consultation->setCreatedAt(new \DateTime()); 
        $consultation->setStatut(0); // Statut par défaut

        // Enregistrement de la consultation
        $entityManager->persist($consultation);
        $entityManager->flush();

        return $consultation;
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
