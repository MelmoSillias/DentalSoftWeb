<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Appreciation;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appreciation>
 */
class AppreciationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appreciation::class);
    }

    /** @return Appreciation[] */
    public function findByPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Appreciation[] */
    public function findPublishedByPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->andWhere('a.isPublished = :published')
            ->setParameter('patient', $patient)
            ->setParameter('published', true)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByConsultation(Consultation $consultation): ?Appreciation
    {
        return $this->createQueryBuilder('a')
            ->where('a.consultation = :consultation')
            ->setParameter('consultation', $consultation)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Appreciation[] */
    public function findLatestForAdmin(int $limit = 200): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')->addSelect('p')
            ->leftJoin('a.consultation', 'c')->addSelect('c')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return array{total:int,anonymous:int,published:int,averageRating:float} */
    public function getAdminStats(): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS total')
            ->addSelect('SUM(CASE WHEN a.isAnonymous = true THEN 1 ELSE 0 END) AS anonymous')
            ->addSelect('SUM(CASE WHEN a.isPublished = true THEN 1 ELSE 0 END) AS published')
            ->addSelect('AVG(a.rating) AS averageRating');

        $row = $qb->getQuery()->getOneOrNullResult() ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'anonymous' => (int) ($row['anonymous'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'averageRating' => round((float) ($row['averageRating'] ?? 0.0), 2),
        ];
    }

    /** @return Appreciation[] */
    public function findPublishedForPublic(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return array{total:int,averageRating:float} */
    public function getPublicStats(): array
    {
        $row = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS total')
            ->addSelect('AVG(a.rating) AS averageRating')
            ->where('a.isPublished = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getOneOrNullResult() ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'averageRating' => round((float) ($row['averageRating'] ?? 0.0), 2),
        ];
    }
}
