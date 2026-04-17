<?php

namespace App\Repository;

use App\Entity\FicheMedicale;
use App\Entity\FicheMedicaleValeur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FicheMedicaleValeur>
 */
class FicheMedicaleValeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMedicaleValeur::class);
    }

    /** @return array<string, FicheMedicaleValeur> */
    public function findIndexedValuesForFiche(FicheMedicale|int $fiche): array
    {
        $rows = $this->createQueryBuilder('v')
            ->leftJoin('v.champ', 'c')->addSelect('c')
            ->leftJoin('c.section', 's')->addSelect('s')
            ->leftJoin('s.onglet', 'o')->addSelect('o')
            ->andWhere('v.ficheMedicale = :fiche')
            ->setParameter('fiche', $fiche)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $code = $row->getChamp()?->getCode();
            if (!$code) {
                continue;
            }

            $indexed[$code] = $row;
        }

        return $indexed;
    }
}