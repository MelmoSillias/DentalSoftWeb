<?php

namespace App\IdentityAccess\Entity;

use App\Billing\Entity\Transaction;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InpsRemittance
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private InpsQuarter $quarter;
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $paidAt;
    #[ORM\Column(length: 255)]
    private string $beneficiary;
    #[ORM\Column(length: 120)]
    private string $reference;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;
    #[ORM\Column(length: 64, unique: true)]
    private string $requestId;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false)]
    private Transaction $transaction;
    #[ORM\Column(type: 'json')]
    private array $audit;

    public function __construct(InpsQuarter $quarter, float $amount, \DateTimeImmutable $paidAt, string $beneficiary, string $reference, ?string $note, string $requestId, Transaction $transaction, array $audit)
    {
        $this->quarter = $quarter;
        $this->amount = (string) $amount;
        $this->paidAt = $paidAt;
        $this->beneficiary = $beneficiary;
        $this->reference = $reference;
        $this->note = $note;
        $this->requestId = $requestId;
        $this->transaction = $transaction;
        $this->audit = $audit;
    }
    public function getId(): ?int { return $this->id; }
    public function getAmount(): float { return (float) $this->amount; }
    public function getTransaction(): Transaction { return $this->transaction; }
    public function getQuarter(): InpsQuarter { return $this->quarter; }
    public function cancel(string $reason, string $actor): void
    {
        $this->transaction->cancel($reason);
        $this->audit[] = ['action' => 'cancelled', 'reason' => $reason, 'actor' => $actor, 'at' => date(DATE_ATOM)];
    }
    public function toArray(): array
    {
        return ['id' => $this->id, 'amount' => $this->getAmount(), 'paidAt' => $this->paidAt->format('Y-m-d'),
            'beneficiary' => $this->beneficiary, 'reference' => $this->reference, 'note' => $this->note,
            'status' => $this->transaction->getValidationStatus(), 'transactionId' => $this->transaction->getId(),
            'paymentMethod' => $this->transaction->getModeDePaiement()?->getLibelle(), 'audit' => $this->audit];
    }
}
