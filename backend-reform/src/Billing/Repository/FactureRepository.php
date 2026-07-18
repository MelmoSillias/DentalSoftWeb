<?php

namespace App\Billing\Repository;

use App\Billing\Entity\Facture;
use App\Patient\Entity\Patient;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * Factures dont le reste à payer est strictement positif.
     * Classique : total actes − paiements facture.
     * Assurance : part patient uniquement − encaissements patient (transactions patient_insurance).
     *
     * @return Facture[]
     */
    public function findUnpaidClassicFactures(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?int $patientId = null,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT f.id
            FROM facture f
            INNER JOIN consultation c ON c.id = f.consultation_id
            LEFT JOIN facture_assurance fa ON fa.consultation_id = c.id
            LEFT JOIN (
                SELECT a.consultation_id,
                       SUM(GREATEST(COALESCE(a.quantite, 1), 1) * COALESCE(a.prix, 0)) AS actes_total
                FROM acte_medical a
                GROUP BY a.consultation_id
            ) actes ON actes.consultation_id = c.id
            WHERE (
                CASE
                    WHEN fa.id IS NOT NULL THEN
                        GREATEST(0,
                            (
                                COALESCE(actes.actes_total, 0)
                                + CASE
                                    WHEN fa.is_consultation_payante = 1 THEN COALESCE(fa.consultation_amount, 0)
                                    ELSE 0
                                  END
                            )
                            - CASE
                                WHEN fa.coverage_rate IS NULL THEN 0
                                ELSE LEAST(
                                    COALESCE(actes.actes_total, 0)
                                    + CASE
                                        WHEN fa.is_consultation_payante = 1 THEN COALESCE(fa.consultation_amount, 0)
                                        ELSE 0
                                      END,
                                    (
                                        COALESCE(actes.actes_total, 0)
                                        + CASE
                                            WHEN fa.is_consultation_payante = 1 THEN COALESCE(fa.consultation_amount, 0)
                                            ELSE 0
                                          END
                                    ) * GREATEST(0, LEAST(100, fa.coverage_rate)) / 100
                                )
                              END
                            - COALESCE((
                                SELECT SUM(p2.montant)
                                FROM paiement p2
                                LEFT JOIN transaction t ON t.paiement_id = p2.id
                                WHERE p2.facture_assurance_id = fa.id
                                  AND (t.id IS NULL OR t.validation_status = :validated)
                            ), 0)
                        )
                    ELSE
                        GREATEST(0,
                            COALESCE(actes.actes_total, 0)
                            - COALESCE((
                                SELECT SUM(p.montant)
                                FROM paiement p
                                LEFT JOIN transaction t ON t.paiement_id = p.id
                                WHERE p.facture_id = f.id
                                  AND (t.id IS NULL OR t.validation_status = :validated)
                            ), 0)
                        )
                END
            ) > 0
        SQL;

        $params = ['validated' => 'validated'];

        if ($patientId !== null) {
            $sql .= ' AND c.patient_id = :patientId';
            $params['patientId'] = $patientId;
        }

        if ($start !== null && $end !== null) {
            $sql .= ' AND f.date_facture BETWEEN :start AND :end';
            $params['start'] = $start->format('Y-m-d H:i:s');
            $params['end'] = $end->format('Y-m-d H:i:s');
        }

        $sql .= ' ORDER BY f.date_facture ASC, f.id ASC';

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $conn->fetchAllAssociative($sql, $params),
        );

        if ($ids === []) {
            return [];
        }

        /** @var Facture[] $factures */
        $factures = $this->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('c.factureAssurance', 'fa')->addSelect('fa')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $facturesById = [];
        foreach ($factures as $facture) {
            $facturesById[$facture->getId()] = $facture;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($facturesById[$id])) {
                $ordered[] = $facturesById[$id];
            }
        }

        return $ordered;
    }

    /** @return Facture[] */
    public function findByPortalPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->where('p = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('f.dateFacture', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
