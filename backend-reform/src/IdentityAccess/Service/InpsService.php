<?php

namespace App\IdentityAccess\Service;

use App\Billing\Entity\Transaction;
use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\InpsQuarter;
use App\IdentityAccess\Entity\InpsRemittance;
use App\IdentityAccess\Entity\SalaryPayment;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class InpsService
{
    public function __construct(private EntityManagerInterface $em, private ModeDePaiementRepository $modes, private Security $security) {}

    public function actor(): string { return $this->security->getUser()?->getUserIdentifier() ?? 'system'; }

    public function quarterId(int $year, int $month): int
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) throw new \InvalidArgumentException('Période invalide.');
        return $year * 10 + (int) ceil($month / 3);
    }

    public function lockOpen(int $year, int $month): InpsQuarter
    {
        $quarter = $this->em->find(InpsQuarter::class, $this->quarterId($year, $month), LockMode::PESSIMISTIC_WRITE);
        if (!$quarter) throw new \InvalidArgumentException('Période INPS indisponible. Appliquez les migrations.');
        $this->em->refresh($quarter);
        if ($quarter->isClosed()) throw new \InvalidArgumentException('Ce trimestre est clôturé. Réouvrez-le avec un motif avant toute correction.');
        return $quarter;
    }

    public function overview(int $year, int $quarter): array
    {
        if ($quarter < 1 || $quarter > 4) throw new \InvalidArgumentException('Trimestre invalide.');
        $id = $this->quarterId($year, ($quarter - 1) * 3 + 1);
        $period = $this->em->find(InpsQuarter::class, $id);
        $payments = $this->em->getRepository(SalaryPayment::class)->createQueryBuilder('p')
            ->join('p.employe', 'e')->addSelect('e')
            ->where('p.year = :year AND p.month BETWEEN :start AND :end AND p.status = :status AND p.frequenceSnapshot = :frequency')
            ->setParameter('year', $year)->setParameter('start', ($quarter - 1) * 3 + 1)->setParameter('end', $quarter * 3)
            ->setParameter('status', 'active')->setParameter('frequency', 'mensuel')
            ->orderBy('p.createdAt', 'ASC')->addOrderBy('p.id', 'ASC')->getQuery()->getResult();
        $employees = $seen = $versions = [];
        $due = 0.0;
        foreach ($payments as $payment) {
            $employee = $payment->getEmploye();
            $key = $employee->getId() . ':' . $payment->getMonth();
            $versions[] = [$payment->getId(), $payment->getVersion()];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $employees[$employee->getId()] ??= ['id' => $employee->getId(), 'name' => $employee->getFullName(), 'months' => [], 'total' => 0];
            $employees[$employee->getId()]['months'][$payment->getMonth()] = $payment->getInps();
            $employees[$employee->getId()]['total'] += $payment->getInps();
            $due += $payment->getInps();
        }
        $remittances = $this->em->getRepository(InpsRemittance::class)->findBy(['quarter' => $id], ['id' => 'ASC']);
        $remitted = 0.0;
        foreach ($remittances as $remittance) {
            if ($remittance->getTransaction()->isValidated()) $remitted += $remittance->getAmount();
            $versions[] = [$remittance->getId(), $remittance->getTransaction()->getValidationStatus()];
        }
        $due = round($due, 2);
        $remitted = round($remitted, 2);
        return ['year' => $year, 'quarter' => $quarter, 'closed' => $period?->isClosed() ?? false,
            'audit' => $period?->getAudit() ?? [],
            'due' => $due, 'remitted' => $remitted, 'remaining' => max(0, round($due - $remitted, 2)),
            'surplus' => max(0, round($remitted - $due, 2)), 'employees' => array_values($employees),
            'remittances' => array_map(fn ($r) => $r->toArray(), $remittances),
            'fingerprint' => hash('sha256', json_encode([$versions, $due, $remitted, $period?->isClosed()]))];
    }

    public function assertCorrectionConfirmed(SalaryPayment $payment, float $newInps, array $payload): void
    {
        if ($payment->getInps() === $newInps) return;
        $state = $this->overview($payment->getYear(), (int) ceil($payment->getMonth() / 3));
        if ($state['remitted'] <= 0) return;
        if (($payload['inpsFingerprint'] ?? '') !== $state['fingerprint']) {
            throw new \InvalidArgumentException('Des remises INPS existent sur ce trimestre. Actualisez le détail et confirmez la régularisation. Les remises et leurs sorties de trésorerie seront conservées.');
        }
    }

    public function remit(array $payload): array
    {
        return $this->em->wrapInTransaction(function () use ($payload) {
            $year = (int) ($payload['year'] ?? 0);
            $quarter = (int) ($payload['quarter'] ?? 0);
            if ($quarter < 1 || $quarter > 4) throw new \InvalidArgumentException('Trimestre invalide.');
            $period = $this->lockOpen($year, ($quarter - 1) * 3 + 1);
            $requestId = trim((string) ($payload['requestId'] ?? ''));
            if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $requestId)) throw new \InvalidArgumentException('Identifiant de requête invalide.');
            $previous = $this->em->getRepository(InpsRemittance::class)->findOneBy(['requestId' => $requestId]);
            if ($previous) {
                $saved = $previous->toArray();
                if (!$previous->getTransaction()->isValidated() || $previous->getQuarter()->getId() !== $period->getId() || $previous->getAmount() !== (float) ($payload['amount'] ?? 0) || $saved['beneficiary'] !== trim((string) ($payload['beneficiary'] ?? '')) || $saved['paidAt'] !== ($payload['paidAt'] ?? null) || $previous->getTransaction()->getModeDePaiement()?->getId() !== (int) ($payload['paymentMethodId'] ?? 0)) throw new \InvalidArgumentException('Identifiant déjà utilisé pour une autre remise ou une remise annulée. Rouvrez le formulaire.');
                return $this->overview($year, $quarter);
            }
            $state = $this->overview($year, $quarter);
            if (($payload['fingerprint'] ?? '') !== $state['fingerprint']) throw new \InvalidArgumentException('La période a changé. Actualisez avant de confirmer la remise.');
            $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($amount === false || !is_finite($amount) || $amount <= 0 || round($amount, 2) !== $amount || $amount > $state['remaining']) throw new \InvalidArgumentException('Le montant doit être positif et ne pas dépasser le reste à remettre.');
            $beneficiary = trim((string) ($payload['beneficiary'] ?? ''));
            $reference = trim((string) ($payload['reference'] ?? ''));
            if ($beneficiary === '' || mb_strlen($beneficiary) > 255 || mb_strlen($reference) > 120) throw new \InvalidArgumentException('Bénéficiaire requis ou référence trop longue.');
            $mode = $this->modes->find((int) ($payload['paymentMethodId'] ?? 0));
            if (!$mode || !$mode->isActif()) throw new \InvalidArgumentException('Mode de règlement invalide ou inactif.');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($payload['paidAt'] ?? ''));
            if (!$date || $date->format('Y-m-d') !== ($payload['paidAt'] ?? '')) throw new \InvalidArgumentException('Date invalide.');
            $reference = $reference ?: 'INPS-' . $year . '-T' . $quarter . '-' . substr($requestId, 0, 8);
            if (!empty($payload['existingTransactionId'])) {
                $transaction = $this->em->find(Transaction::class, (int) $payload['existingTransactionId'], LockMode::PESSIMISTIC_WRITE);
                if (!$transaction) throw new \InvalidArgumentException('Transaction existante introuvable.');
                $this->em->refresh($transaction);
                if ($transaction->getSource() !== null || !$transaction->isValidated() || $transaction->getType() !== 'Depense' || (float) $transaction->getMontant() !== $amount || $transaction->getDateTransaction()?->format('Y-m-d') !== $date->format('Y-m-d') || $transaction->getModeDePaiement()?->getId() !== $mode->getId() || $transaction->getFacture() || $transaction->getPaiement() || $transaction->getConsultation() || $transaction->getLotFactureAssurance()) throw new \InvalidArgumentException('La transaction doit être une dépense manuelle validée, non liée, de même montant, date et mode.');
                $transaction->setSource('inps');
            } else {
                $transaction = (new Transaction())->setSource('inps')->setType('Depense')->setMontant((string) $amount)
                    ->setMotif('Remise INPS à la direction')->setDescription($reference . ' · ' . $beneficiary)
                    ->setDateTransaction($date)->setModeDePaiement($mode)->markValidated($date);
            }
            $record = new InpsRemittance($period, $amount, $date, $beneficiary, $reference, $payload['note'] ?? null, $requestId, $transaction,
                [['action' => 'created', 'existingTransactionId' => $payload['existingTransactionId'] ?? null, 'actor' => $this->actor(), 'at' => date(DATE_ATOM)]]);
            $this->em->persist($transaction);
            $this->em->persist($record);
            $this->em->flush();
            return $this->overview($year, $quarter);
        });
    }

    public function cancel(int $id, array $payload): array
    {
        return $this->em->wrapInTransaction(function () use ($id, $payload) {
            $record = $this->em->find(InpsRemittance::class, $id);
            if (!$record) throw new \InvalidArgumentException('Remise introuvable.');
            $periodId = $record->getQuarter()->getId();
            $year = intdiv($periodId, 10);
            $quarter = $periodId % 10;
            $this->lockOpen($year, ($quarter - 1) * 3 + 1);
            $this->em->refresh($record->getTransaction());
            $state = $this->overview($year, $quarter);
            if (($payload['fingerprint'] ?? '') !== $state['fingerprint']) throw new \InvalidArgumentException('La période a changé. Actualisez avant d’annuler.');
            $reason = trim((string) ($payload['reason'] ?? ''));
            if ($reason === '' || ($payload['entryErrorConfirmed'] ?? false) !== true) throw new \InvalidArgumentException('Confirmez une erreur de saisie et indiquez son motif. Une remise réellement effectuée doit être conservée.');
            if ($record->getTransaction()->isValidated()) $record->cancel($reason, $this->actor());
            $this->em->flush();
            return $this->overview($year, $quarter);
        });
    }

    public function setClosed(int $year, int $quarter, array $payload): array
    {
        return $this->em->wrapInTransaction(function () use ($year, $quarter, $payload) {
            if ($quarter < 1 || $quarter > 4) throw new \InvalidArgumentException('Trimestre invalide.');
            $id = $this->quarterId($year, ($quarter - 1) * 3 + 1);
            $period = $this->em->find(InpsQuarter::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$period) throw new \InvalidArgumentException('Période indisponible.');
            $this->em->refresh($period);
            $state = $this->overview($year, $quarter);
            if (($payload['fingerprint'] ?? '') !== $state['fingerprint']) throw new \InvalidArgumentException('La période a changé. Actualisez.');
            $closed = ($payload['closed'] ?? false) === true;
            if (trim((string) ($payload['reason'] ?? '')) === '') throw new \InvalidArgumentException('Un motif est requis.');
            if ($closed && ($state['remaining'] > 0 || $state['surplus'] > 0 || new \DateTimeImmutable() < new \DateTimeImmutable(sprintf('%04d-%02d-01', $year + ($quarter === 4 ? 1 : 0), $quarter === 4 ? 1 : $quarter * 3 + 1)))) throw new \InvalidArgumentException('Seul un trimestre terminé et soldé peut être clôturé.');
            $period->record(['action' => $closed ? 'closed' : 'reopened', 'reason' => $payload['reason'], 'actor' => $this->actor(), 'at' => date(DATE_ATOM)]);
            $period->setClosed($closed);
            $this->em->flush();
            return $this->overview($year, $quarter);
        });
    }
}
