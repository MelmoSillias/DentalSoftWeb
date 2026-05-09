<?php

namespace App\Billing\Entity;

use App\Billing\Repository\FactureRepository;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'facture')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Consultation $consultation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isReglee = false;

    #[ORM\ManyToOne(targetEntity: Assurance::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Assurance $assurance = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $tauxCouverture = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRecouvre = false;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $insuranceStatus = 'pending';

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;

        return $this;
    }

    public function getMontantTotal(): float
    {
        return (float) ($this->computeMontantsFromConsultation()['montantTotal'] ?? 0.0);
    }

    public function setMontantTotal(float $montantTotal): static
    {
        // Totals are computed on the fly from consultation actes.
        return $this;
    }

    public function getMontantPatient(): float
    {
        return (float) ($this->computeMontantsFromConsultation()['montantPatient'] ?? 0.0);
    }

    public function setMontantPatient(float $montantPatient): static
    {
        // Totals are computed on the fly from consultation actes.
        return $this;
    }

    public function getMontantAssurance(): float
    {
        return (float) ($this->computeMontantsFromConsultation()['montantAssurance'] ?? 0.0);
    }

    public function setMontantAssurance(float $montantAssurance): static
    {
        // Totals are computed on the fly from consultation actes.
        return $this;
    }

    public function getRestePatient(): float
    {
        return (float) ($this->computeMontantsFromConsultation()['restePatient'] ?? 0.0);
    }

    public function setRestePatient(float $restePatient): static
    {
        // Totals are computed on the fly from consultation actes.
        return $this;
    }

    public function isReglee(): bool
    {
        $montants = $this->computeMontantsFromConsultation();
        if (((float) ($montants['montantTotal'] ?? 0.0)) > 0.0) {
            return ((float) ($montants['restePatient'] ?? 0.0)) <= 0.0;
        }

        return $this->isReglee;
    }

    public function setIsReglee(bool $isReglee): static
    {
        $this->isReglee = $isReglee;

        return $this;
    }

    public function getAssurance(): ?Assurance
    {
        return $this->assurance;
    }

    public function setAssurance(?Assurance $assurance): static
    {
        $this->assurance = $assurance;

        return $this;
    }

    public function getTauxCouverture(): ?float
    {
        return $this->tauxCouverture;
    }

    public function setTauxCouverture(?float $tauxCouverture): static
    {
        $this->tauxCouverture = $tauxCouverture;

        return $this;
    }

    public function isRecouvre(): bool
    {
        return $this->isRecouvre;
    }

    public function setIsRecouvre(bool $isRecouvre): static
    {
        $this->isRecouvre = $isRecouvre;

        return $this;
    }

    public function getInsuranceStatus(): string
    {
        return $this->insuranceStatus;
    }

    public function setInsuranceStatus(string $insuranceStatus): static
    {
        $this->insuranceStatus = $insuranceStatus;

        return $this;
    }

    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setFacture($this);
        }

        return $this;
    }

    public function removePaiement(Paiement $paiement): static
    {
        if ($this->paiements->removeElement($paiement) && $paiement->getFacture() === $this) {
            $paiement->setFacture(null);
        }

        return $this;
    }

    private function normalizeCoverageRate(?float $rate): ?float
    {
        if ($rate === null) {
            return null;
        }

        return max(0.0, min(100.0, $rate));
    }

    public function resolveCoverageRate(): ?float
    {
        $ownRate = $this->normalizeCoverageRate($this->tauxCouverture);
        if ($ownRate !== null) {
            return $ownRate;
        }

        return $this->normalizeCoverageRate($this->consultation?->getTauxCouverture());
    }

    public function computePatientPaidAmount(): float
    {
        $paid = 0.0;
        foreach ($this->getPaiements() as $payment) {
            $status = $payment->getTransaction()?->getValidationStatus();
            if ($status !== null && $status !== 'validated') {
                continue;
            }

            $paid += (float) $payment->getMontant();
        }

        return $paid;
    }

    public function computeMontantsFromConsultation(?float $overrideCoverageRate = null): array
    {
        $total = 0.0;
        $consultation = $this->getConsultation();

        if ($consultation) {
            foreach ($consultation->getActes() as $acte) {
                $qty = max(1, (int) ($acte->getQuantite() ?? 1));
                $price = (float) ($acte->getPrix() ?? 0);
                $total += $qty * $price;
            }
        }

        $rate = $this->normalizeCoverageRate($overrideCoverageRate ?? $this->resolveCoverageRate());
        $assuranceAmount = $rate !== null ? ($total * $rate) / 100 : 0.0;
        $assuranceAmount = max(0.0, min($total, $assuranceAmount));

        $patientAmount = max(0.0, $total - $assuranceAmount);
        $patientPaid = max(0.0, $this->computePatientPaidAmount());
        $patientRemaining = max(0.0, $patientAmount - $patientPaid);

        return [
            'montantTotal' => $total,
            'montantAssurance' => $assuranceAmount,
            'montantPatient' => $patientAmount,
            'patientPaid' => $patientPaid,
            'restePatient' => $patientRemaining,
            'tauxCouverture' => $rate,
        ];
    }

    public function buildLignesFromConsultation(): array
    {
        $consultation = $this->getConsultation();
        if (!$consultation) {
            return [];
        }

        return array_map(static function (ActeMedical $acte): array {
            $quantite = max(1, (int) ($acte->getQuantite() ?? 1));
            $prix = (float) ($acte->getPrix() ?? 0);
            $type = trim((string) ($acte->getType() ?? ''));
            $description = trim((string) ($acte->getDescription() ?? ''));
            if ($description === '') {
                $description = $type;
            }

            return [
                'designation' => $type,
                'type' => $type,
                'description' => $description,
                'qte' => $quantite,
                'quantite' => $quantite,
                'montant' => $prix,
                'prix' => $prix,
                'dent' => (string) ($acte->getDent() ?? ''),
                'total' => $quantite * $prix,
            ];
        }, $consultation->getActes()->toArray());
    }
}
