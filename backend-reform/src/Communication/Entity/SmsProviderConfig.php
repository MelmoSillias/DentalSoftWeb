<?php

namespace App\Communication\Entity;

use App\Communication\Repository\SmsProviderConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SmsProviderConfigRepository::class)]
class SmsProviderConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $provider = 'orange';

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clientSecretEncrypted = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senderAddress = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $approvedSenderNames = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $patientPreferenceBypass = null;

    #[ORM\Column(length: 255)]
    private string $baseUrl = 'https://api.orange.com';

    #[ORM\Column(length: 255)]
    private string $oauthUrl = 'https://api.orange.com/oauth/v3/token';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webhookBaseUrl = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 2])]
    private int $callbackNotifyType = 2;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->approvedSenderNames = [];
        $this->patientPreferenceBypass = [];
    }

    public function getId(): ?int { return $this->id; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): static { $this->provider = $provider; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): static { $this->enabled = $enabled; return $this; }
    public function getClientId(): ?string { return $this->clientId; }
    public function setClientId(?string $clientId): static { $this->clientId = $clientId; return $this; }
    public function getClientSecretEncrypted(): ?string { return $this->clientSecretEncrypted; }
    public function setClientSecretEncrypted(?string $clientSecretEncrypted): static { $this->clientSecretEncrypted = $clientSecretEncrypted; return $this; }
    public function getSenderAddress(): ?string { return $this->senderAddress; }
    public function setSenderAddress(?string $senderAddress): static { $this->senderAddress = $senderAddress; return $this; }
    public function getSenderName(): ?string { return $this->senderName; }
    public function setSenderName(?string $senderName): static { $this->senderName = $senderName; return $this; }
    public function getApprovedSenderNames(): array
    {
        return array_values(array_filter($this->approvedSenderNames ?? [], static fn ($value) => is_string($value) && trim($value) !== ''));
    }

    public function setApprovedSenderNames(array $approvedSenderNames): static
    {
        $this->approvedSenderNames = array_values($approvedSenderNames);

        return $this;
    }

    public function getPatientPreferenceBypass(): array
    {
        return $this->patientPreferenceBypass ?? [];
    }

    public function setPatientPreferenceBypass(array $patientPreferenceBypass): static
    {
        $this->patientPreferenceBypass = $patientPreferenceBypass;

        return $this;
    }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function setBaseUrl(string $baseUrl): static { $this->baseUrl = rtrim($baseUrl, '/'); return $this; }
    public function getOauthUrl(): string { return $this->oauthUrl; }
    public function setOauthUrl(string $oauthUrl): static { $this->oauthUrl = $oauthUrl; return $this; }
    public function getWebhookBaseUrl(): ?string { return $this->webhookBaseUrl; }
    public function setWebhookBaseUrl(?string $webhookBaseUrl): static
    {
        $this->webhookBaseUrl = $webhookBaseUrl !== null ? rtrim($webhookBaseUrl, '/') : null;

        return $this;
    }
    public function getCallbackNotifyType(): int { return $this->callbackNotifyType; }
    public function setCallbackNotifyType(int $callbackNotifyType): static
    {
        $this->callbackNotifyType = max(1, min(2, $callbackNotifyType));

        return $this;
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}