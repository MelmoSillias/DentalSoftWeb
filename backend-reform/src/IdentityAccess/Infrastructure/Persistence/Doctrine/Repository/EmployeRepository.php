<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employe>
 */
class EmployeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    public function createEmployeeWithOptionalUser(array $data): Employe
    {
        $entityManager = $this->getEntityManager();

        // Création de l'Employee et affectation des valeurs
        $employee = new Employe();
        $employee->setNom($data['nom']);
        $employee->setPrenom($data['prenom']);
        $employee->setTelephone($data['telephone'] ?? null);
        $employee->setFonction($data['fonction']);
        $employee->setType($data['type']);
        $employee->setDateEmbauche(new \DateTime($data['dateEmbauche']));
        $employee->setComingDaysInWeek($data['comingDays'] ?? []);

        // Vérifier si le type nécessite la création d'un utilisateur
        $typeLower = strtolower($data['type']);
        if (in_array($typeLower, ['medecin', 'receptionniste', 'admin']) && !empty($data['username'])) {
            $user = new User();
            $user->setUsername($data['username']);
            // Mot de passe par défaut : "123456" (haché pour la sécurité)
            $user->setPassword(password_hash('123456', PASSWORD_DEFAULT));
            // On peut associer l'utilisateur à l'employé (si relation bidirectionnelle définie dans vos entités)
            $employee->setUser($user);
            $entityManager->persist($user);
        }

        $entityManager->persist($employee);
        $entityManager->flush();

        return $employee;
    }

    public function findID(int $id): ?array
    {
        return $this->find($id);
    }

    public function FindAllMedecin(): ?array
    {
        return $this->findByTypeInsensitive('medecin');
    }

    public function findAllInfirmiers(): array
    {
        return $this->findByTypeInsensitive('infirmier');
    }

    public function findByTypeInsensitive(string $type): array
    {
        return $this->createQueryBuilder('e')
            ->where('LOWER(e.type) = LOWER(:type)')
            ->setParameter('type', $type)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function FindEmployeeByid(): ?array
    {
        return $this->createQueryBuilder('e')
            ->where('e.type = :type')
            ->setParameter('type', "Medecin")
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les employés triés par identifiant.
     *
     * @return Employe[]
     */
    public function findAllEmployees(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.dateEmbauche <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findDistinctTypes(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.type')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function updateEmployee(Employe $employee): void
    {
        $this->persist($employee);
        $this->flush();
    }

    public function findEmployeesWithPagination(int $start, int $length, string $searchValue): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($searchValue) {
            $qb->where('e.nom LIKE :search')
               ->orWhere('e.prenom LIKE :search')
               ->orWhere('e.fonction LIKE :search')
               ->orWhere('e.type LIKE :search')
               ->orderBy('e.type')
               ->addOrderBy('e.nom', 'ASC')
               ->setParameter('search', '%' . $searchValue . '%');
        }

        $qb->orderBy('e.type', 'ASC')
           ->addOrderBy('e.nom', 'ASC');
        return $qb->setFirstResult($start)
                  ->setMaxResults($length)
                  ->getQuery()
                  ->getResult();
    }

    public function countFiltered(string $searchValue): int
    {
        $qb = $this->createQueryBuilder('e')
                   ->select('COUNT(e.id)');

        if ($searchValue) {
            $qb->where('e.nom LIKE :search')
               ->orWhere('e.prenom LIKE :search')
               ->orWhere('e.fonction LIKE :search')
               ->orWhere('e.type LIKE :search')
               ->orderBy('e.type')
               ->setParameter('search', '%' . $searchValue . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.email = :email')
            ->setParameter('email', $email);

        if ($excludeId !== null) {
            $qb->andWhere('e.id <> :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function matriculeExists(string $matricule, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.matricule = :matricule')
            ->setParameter('matricule', $matricule);

        if ($excludeId !== null) {
            $qb->andWhere('e.id <> :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    

    public function sumValeurSalaireByType(string $typeSalaire): float
{
    return $this->createQueryBuilder('e')
        ->select('SUM(e.valeurSalaire)')
        ->andWhere('e.typeSalaire = :typeSalaire')
        ->setParameter('typeSalaire', $typeSalaire)
        ->getQuery()
        ->getSingleScalarResult();
}

    //    /**
    //     * @return Employe[] Returns an array of Employe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Employe
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
