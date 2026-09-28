<?php

namespace App\Billing\Entity;

use App\Billing\Repository\ServiceCabinetRepository;
use App\ClinicalRecord\Entity\FicheMedicale;
use App\Patient\Entity\Patient;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceCabinetRepository::class)]
#[ORM\Table(name: 'service_cabinet')]
class ServiceCabinet
{
    public const STATUT_EFFECTUE = 'effectue';
    public const STATUT_ANNULE = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(targetEntity: FicheMedicale::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FicheMedicale $ficheMedicale = null;

    #[ORM\Column(length: 255)]
    private string $designation = '';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $quantite = 1;

    #[ORM\Column(type: Types::FLOAT)]
    private float $prix = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $montant = 0.0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateRealisation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUT_EFFECTUE])]
    private string $statut = self::STATUT_EFFECTUE;

    #[ORM\OneToOne(mappedBy: 'service', targetEntity: FactureCabinet::class, cascade: ['persist'])]
    private ?FactureCabinet $facture = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getFicheMedicale(): ?FicheMedicale
    {
        return $this->ficheMedicale;
    }

    public function setFicheMedicale(?FicheMedicale $ficheMedicale): static
    {
        $this->ficheMedicale = $ficheMedicale;

        return $this;
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = max(1, $quantite);

        return $this;
    }

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = max(0.0, $prix);

        return $this;
    }

    public function getMontant(): float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = max(0.0, $montant);

        return $this;
    }

    public function getDateRealisation(): ?\DateTimeInterface
    {
        return $this->dateRealisation;
    }

    public function setDateRealisation(\DateTimeInterface $dateRealisation): static
    {
        $this->dateRealisation = $dateRealisation;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = $note !== null ? trim($note) : null;
        $this->note = $note === '' ? null : $note;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut === self::STATUT_ANNULE ? self::STATUT_ANNULE : self::STATUT_EFFECTUE;

        return $this;
    }

    public function isAnnule(): bool
    {
        return $this->statut === self::STATUT_ANNULE;
    }

    public function getFacture(): ?FactureCabinet
    {
        return $this->facture;
    }

    public function setFacture(?FactureCabinet $facture): static
    {
        $this->facture = $facture;

        return $this;
    }
}
