<?php

namespace App\Billing\Entity;

use App\Billing\Repository\TransactionRepository;
use App\CareDelivery\Entity\Consultation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0)]
    private ?string $montant = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateTransaction = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $validated = false;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $validationStatus = 'pending';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $validationComment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(length: 32, options: ['default' => 'direct'])]
    private string $rolePaiement = 'direct';

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tauxPriseEnCharge = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ModeDePaiement $modeDePaiement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Devis $devis = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Consultation $consultation = null;

    #[ORM\OneToOne(inversedBy: 'transaction', cascade: ['persist', 'remove'])]
    private ?PaiementDevis $paiementDevis = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDateTransaction(): ?\DateTimeInterface
    {
        return $this->dateTransaction;
    }

    public function setDateTransaction(\DateTimeInterface $dateTransaction): static
    {
        $this->dateTransaction = $dateTransaction;

        return $this;
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): static
    {
        $this->validated = $validated;
        $this->validationStatus = $validated ? 'validated' : 'pending';

        return $this;
    }

    public function getValidationStatus(): string
    {
        return $this->validationStatus;
    }

    public function setValidationStatus(string $validationStatus): static
    {
        $this->validationStatus = $validationStatus;
        $this->validated = $validationStatus === 'validated';

        return $this;
    }

    public function getValidationComment(): ?string
    {
        return $this->validationComment;
    }

    public function setValidationComment(?string $validationComment): static
    {
        $this->validationComment = $validationComment;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;

        return $this;
    }

    public function getRolePaiement(): string
    {
        return $this->rolePaiement;
    }

    public function setRolePaiement(string $rolePaiement): static
    {
        $this->rolePaiement = $rolePaiement;

        return $this;
    }

    public function getTauxPriseEnCharge(): ?float
    {
        return $this->tauxPriseEnCharge;
    }

    public function setTauxPriseEnCharge(?float $tauxPriseEnCharge): static
    {
        $this->tauxPriseEnCharge = $tauxPriseEnCharge;

        return $this;
    }

    public function getModeDePaiement(): ?ModeDePaiement
    {
        return $this->modeDePaiement;
    }

    public function setModeDePaiement(?ModeDePaiement $mode): static
    {
        $this->modeDePaiement = $mode;
        return $this;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getPaiementDevis(): ?PaiementDevis
    {
        return $this->paiementDevis;
    }

    public function setPaiementDevis(?PaiementDevis $paiementDevis): static
    {
        $this->paiementDevis = $paiementDevis;

        return $this;
    }

    public function markPending(): static
    {
        $this->validated = false;
        $this->validationStatus = 'pending';
        $this->validatedAt = null;
        $this->rejectedAt = null;

        return $this;
    }

    public function markValidated(?\DateTimeImmutable $validatedAt = null): static
    {
        $this->validated = true;
        $this->validationStatus = 'validated';
        $this->validatedAt = $validatedAt ?? new \DateTimeImmutable();
        $this->rejectedAt = null;

        return $this;
    }

    public function markRejected(?string $comment = null, ?\DateTimeImmutable $rejectedAt = null): static
    {
        $this->validated = false;
        $this->validationStatus = 'rejected';
        $this->validationComment = $comment;
        $this->rejectedAt = $rejectedAt ?? new \DateTimeImmutable();
        $this->validatedAt = null;

        return $this;
    }
}