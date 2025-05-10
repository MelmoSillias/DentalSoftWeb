<?php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Patient>
 */
class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    public function FindAllPatients(): array
    {
        $patients = $this->createQueryBuilder('p')
            ->select('p.id, p.nom, p.prenom, p.dateNaissance, p.dateInscription, p.sexe, p.telephone, p.adresse, p.numCarnet')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Calculer l'âge pour chaque patient
        foreach ($patients as &$patient) {
            if ($patient['dateNaissance'] instanceof \DateTimeInterface) {
                $now = new \DateTime();
                $age = $now->diff($patient['dateNaissance'])->y;
                $patient['age'] = $age . ' ans';
            } else {
                $patient['age'] = 'Néant';
            }
        }

        return $patients;
    }


    public function addPatient(array $data): Patient
    {
        $entityManager = $this->getEntityManager();

        // Générer un numéro de carnet unique (par exemple : P20240320001)
        $latestPatient = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $lastNumber = $latestPatient ? (int) substr($latestPatient->getNumCarnet(), -3) + 1 : 1;
        $numCarnet = 'P' . date('Ymd') . str_pad($lastNumber, 3, '0', STR_PAD_LEFT);

        // Création du patient
        $patient = new Patient();
        $patient->setNom($data['nom']);
        $patient->setPrenom($data['prenom']);
        $patient->setDateNaissance(new \DateTime($data['dateNaissance']));
        $patient->setSexe($data['sexe']);
        $patient->setTelephone($data['telephone']);
        $patient->setAdresse($data['adresse']);
        $patient->setNumCarnet($numCarnet);
        $patient->setDateInscription(new \DateTime());

        $entityManager->persist($patient);
        $entityManager->flush();

        return $patient;
    }

    public function findPatientById(int $id): ?array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.nom, p.prenom, p.dateNaissance, p.dateInscription, p.sexe, p.telephone, p.adresse, p.numCarnet')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function updatePatient(int $id, array $data): bool
    {
        $entityManager = $this->getEntityManager();
        $patient = $entityManager->getRepository(Patient::class)->find($id);

        if (!$patient) {
            return false;
        }

        if (isset($data['nom'])) {
            $patient->setNom($data['nom']);
        }
        if (isset($data['prenom'])) {
            $patient->setPrenom($data['prenom']);
        }
        if (isset($data['telephone'])) {
            $patient->setTelephone($data['telephone']);
        }
        if (isset($data['adresse'])) {
            $patient->setAdresse($data['adresse']);
        }

        $entityManager->flush();
        return true;
    }

    // src/Repository/PatientRepository.php
public function findWithMedicalData(int $id): ?Patient
{
    return $this->createQueryBuilder('p')
        ->leftJoin('p.antecedents', 'a')
        ->addSelect('a')
        ->leftJoin('p.consultations', 'c')
        ->addSelect('c')
        ->leftJoin('p.rdvs', 'r')
        ->addSelect('r')
        ->leftJoin('p.traitements', 't')
        ->addSelect('t')
        ->where('p.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getOneOrNullResult();
}

    //    /**
    //     * @return Patient[] Returns an array of Patient objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Patient
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
