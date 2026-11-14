<?php

namespace App\Billing\Application\Handler;

use DateTimeInterface;
use App\Billing\Domain\Entity\Transaction;
use App\Billing\Infrastructure\Persistence\Doctrine\TransactionRepository;
use App\Billing\Application\Mapper\FinanceTransactionMapper;

class FinanceTransactionQueryHandler
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private FinanceTransactionMapper $transactionMapper,
    ) {
    }

    /** @return Transaction[] */
    public function getTransactions(): array
    {
        return $this->transactionRepo->findBy([], ['dateTransaction' => 'DESC']);
    }

    public function getTransactionsByDateRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $transactions = $this->transactionRepo->createQueryBuilder('t')
            ->where('t.dateTransaction BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.dateTransaction', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Transaction $transaction) => $this->transactionMapper->mapTransactionToArray($transaction), $transactions);
    }
}
