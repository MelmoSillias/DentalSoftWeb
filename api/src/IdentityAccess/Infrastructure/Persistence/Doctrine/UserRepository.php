<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Entity\Employe;
use App\Patient\Domain\Entity\Patient;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface, \App\IdentityAccess\Domain\Repository\UserRepositoryInterface
{
    /** Email is accepted only if it identifies exactly one existing account. */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        $matches = $this->createQueryBuilder('u')
            ->leftJoin(Employe::class, 'e', 'WITH', 'e.user = u')
            ->leftJoin(Patient::class, 'p', 'WITH', 'p.portalUser = u')
            ->where('u.username = :identifier OR LOWER(e.email) = :email OR LOWER(p.email) = :email')
            ->setParameter('identifier', $identifier)
            ->setParameter('email', mb_strtolower(trim($identifier)))
            ->setMaxResults(2)->getQuery()->getResult();
        if (count($matches) !== 1) throw new UserNotFoundException();
        return $matches[0];
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<User>
     */
    public function findByRole(string $role): array
    {
        return $this->findByRoles([$role]);
    }

    /**
     * @param list<string> $roles
     * @return list<User>
     */
    public function findByRoles(array $roles): array
    {
        $roles = array_values(array_filter(array_unique($roles)));

        if ($roles === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('u');
        $orX = $qb->expr()->orX();

        foreach ($roles as $index => $role) {
            $param = 'role' . $index;
            $orX->add(sprintf('u.roles LIKE :%s', $param));
            $qb->setParameter($param, '%"' . $role . '"%');
        }

        $qb->andWhere($orX);

        return $qb->getQuery()->getResult();
    }


    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
