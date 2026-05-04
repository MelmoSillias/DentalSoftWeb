<?php

namespace App\IdentityAccess\Entity;

use App\IdentityAccess\Repository\SalaryPaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalaryPaymentRepository::class)]
class SalaryPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'salaryPayments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $employe = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $year;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $month;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodEnd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $baseAmount = null;

    #[ORM\Column(length: 32)]
    private string $salaryTypeSnapshot = 'non_defini';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salaryValueSnapshot = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $calculatedAmount = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $paidAmount = '0';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): self
    {
        $this->employe = $employe;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

        return $this;
    }

    public function getPeriodStart(): ?\DateTimeInterface
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeInterface $periodStart): self
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeInterface
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeInterface $periodEnd): self
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getBaseAmount(): ?float
    {
        return $this->baseAmount === null ? null : (float) $this->baseAmount;
    }

    public function setBaseAmount(?float $baseAmount): self
    {
        $this->baseAmount = $baseAmount === null ? null : (string) $baseAmount;

        return $this;
    }

    public function getSalaryTypeSnapshot(): string
    {
        return $this->salaryTypeSnapshot;
    }

    public function setSalaryTypeSnapshot(string $salaryTypeSnapshot): self
    {
        $this->salaryTypeSnapshot = $salaryTypeSnapshot;

        return $this;
    }

    public function getSalaryValueSnapshot(): ?float
    {
        return $this->salaryValueSnapshot === null ? null : (float) $this->salaryValueSnapshot;
    }

    public function setSalaryValueSnapshot(?float $salaryValueSnapshot): self
    {
        $this->salaryValueSnapshot = $salaryValueSnapshot === null ? null : (string) $salaryValueSnapshot;

        return $this;
    }

    public function getCalculatedAmount(): float
    {
        return (float) $this->calculatedAmount;
    }

    public function setCalculatedAmount(float $calculatedAmount): self
    {
        $this->calculatedAmount = (string) $calculatedAmount;

        return $this;
    }

    public function getPaidAmount(): float
    {
        return (float) $this->paidAmount;
    }

    public function setPaidAmount(float $paidAmount): self
    {
        $this->paidAmount = (string) $paidAmount;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTimeInterface $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
