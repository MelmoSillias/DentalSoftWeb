<?php

namespace App\Communication\Entity;

use App\Communication\Repository\SmsLogRepository;
use App\Patient\Entity\Patient;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SmsLogRepository::class)]
#[ORM\Index(columns: ['status'], name: 'idx_sms_log_status')]
#[ORM\Index(columns: ['type'], name: 'idx_sms_log_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_sms_log_created_at')]
class SmsLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 64)]
    private string $phone;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 24)]
    private string $status = 'queued';

    #[ORM\Column(length: 64)]
    private string $type = 'manual';

    #[ORM\Column(length: 64)]
    private string $source = 'manual';

    #[ORM\Column(length: 32)]
    private string $provider = 'orange';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerMessageId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(?Patient $patient): static { $this->patient = $patient; return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): static { $this->provider = $provider; return $this; }
    public function getProviderMessageId(): ?string { return $this->providerMessageId; }
    public function setProviderMessageId(?string $providerMessageId): static { $this->providerMessageId = $providerMessageId; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}