<?php

namespace App\Repository;

use App\Entity\Employe;
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

    public function findOneByPortalIdentifier(string $identifier): ?Patient
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $normalized = mb_strtolower($identifier);
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        $conditions = [
            'LOWER(COALESCE(email, \'\')) = :normalized',
            'LOWER(COALESCE(num_carnet, \'\')) = :normalized',
        ];
        $params = ['normalized' => $normalized];

        if ($digits !== '') {
            $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telephone, ''), ' ', ''), '-', ''), '.', ''), '+', '') = :digits";
            $params['digits'] = $digits;
        }

        if (ctype_digit($identifier)) {
            $conditions[] = 'id = :id';
            $params['id'] = (int) $identifier;
        }

        $sql = sprintf('SELECT id FROM patient WHERE %s ORDER BY id DESC LIMIT 1', implode(' OR ', $conditions));
        $id = $this->getEntityManager()->getConnection()->fetchOne($sql, $params);

        return $id ? $this->find((int) $id) : null;
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

    public function paginatePatients(
        int $page,
        int $limit,
        ?string $term = null,
        ?string $sortField = null,
        ?string $sortOrder = null
    ): array {
        $qb = $this->createQueryBuilder('p');

        if (!empty($term)) {
            $normalized = mb_strtolower(trim($term));
            $like = '%' . $normalized . '%';

            $orX = $qb->expr()->orX(
                'LOWER(p.nom) LIKE :term',
                'LOWER(p.prenom) LIKE :term',
                'LOWER(p.telephone) LIKE :term',
                "LOWER(CONCAT(p.nom, ' ', p.prenom)) LIKE :term",
                "LOWER(CONCAT(p.prenom, ' ', p.nom)) LIKE :term"
            );

            // Si contient des chiffres -> recherche telephone sans fonction SQL non supportee en DQL
            $digits = preg_replace('/\D+/', '', $normalized);
            if (!empty($digits)) {
                $orX->add('p.telephone LIKE :digits');
                $qb->setParameter('digits', '%' . $digits . '%');
            }

            $qb->andWhere($orX)
                ->setParameter('term', $like);
        }

        // COUNT
        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // TRI
        $direction = strtolower($sortOrder ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $sortMap = [
            'nom' => 'p.nom',
            'prenom' => 'p.prenom',
            'telephone' => 'p.telephone',
            'adresse' => 'p.adresse',
            'sexe' => 'p.sexe',
            'dateNaissance' => 'p.dateNaissance',
        ];

        $sortColumn = $sortMap[$sortField ?? ''] ?? 'p.nom';

        $items = $qb
            ->orderBy($sortColumn, $direction)
            ->addOrderBy('p.prenom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    public function paginatePatientsByMedecin(Employe $medecin, int $page, int $limit, ?string $term = null, ?string $sortField = null, ?string $sortOrder = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('EXISTS (SELECT 1 FROM App\\Entity\\Consultation c WHERE c.patient = p AND c.medecin = :medecin) OR EXISTS (SELECT 1 FROM App\\Entity\\Rdv r WHERE r.patient = p AND r.medecin = :medecin)')
            ->setParameter('medecin', $medecin);

        if ($term !== null && $term !== '') {
            $normalizedTerm = mb_strtolower(trim($term));
            $termLike = '%' . $normalizedTerm . '%';
            $digitsOnly = preg_replace('/\D+/', '', $normalizedTerm);

            $orX = $qb->expr()->orX(
                'LOWER(p.nom) LIKE :term',
                'LOWER(p.prenom) LIKE :term',
                'LOWER(CONCAT(COALESCE(p.nom, \'\'), \' \', COALESCE(p.prenom, \'\'))) LIKE :term',
                'LOWER(CONCAT(COALESCE(p.prenom, \'\'), \' \', COALESCE(p.nom, \'\'))) LIKE :term',
                'LOWER(p.adresse) LIKE :term',
                'LOWER(p.telephone) LIKE :term'
            );

            if (!empty($digitsOnly)) {
                $pairs = str_split($digitsOnly, 2);
                $spacedDigits = trim(implode(' ', $pairs));

                $orX->add('p.telephone LIKE :termPhoneDigits');
                $orX->add('p.telephone LIKE :termPhoneSpaced');
                $qb->setParameter('termPhoneDigits', '%' . $digitsOnly . '%');
                $qb->setParameter('termPhoneSpaced', '%' . $spacedDigits . '%');
            }

            $qb->andWhere($orX)
                ->setParameter('term', $termLike);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $direction = strtolower($sortOrder ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'fullname' => 'p.nom',
            'nom' => 'p.nom',
            'prenom' => 'p.prenom',
            'telephone' => 'p.telephone',
            'adresse' => 'p.adresse',
            'sexe' => 'p.sexe',
            'dateNaissance' => 'p.dateNaissance',
        ];
        $sortColumn = $sortMap[$sortField ?? ''] ?? 'p.nom';

        $items = $qb
            ->orderBy($sortColumn, $direction)
            ->addOrderBy('p.prenom', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
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
