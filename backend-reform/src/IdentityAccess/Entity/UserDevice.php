<?php

namespace App\IdentityAccess\Entity;

use App\IdentityAccess\Repository\UserDeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDeviceRepository::class)]
#[ORM\Table(name: 'user_device')]
#[ORM\UniqueConstraint(name: 'uniq_device_identifier', columns: ['device_identifier'])]
#[ORM\Index(name: 'idx_user_device_status', columns: ['status'])]
class UserDevice
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $deviceIdentifier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customName = null;

    #[ORM\Column(length: 80)]
    private string $deviceType = 'desktop';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeviceIdentifier(): ?string
    {
        return $this->deviceIdentifier;
    }

    public function setDeviceIdentifier(string $deviceIdentifier): static
    {
        $this->deviceIdentifier = $deviceIdentifier;

        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): static
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function getCustomName(): ?string
    {
        return $this->customName;
    }

    public function setCustomName(?string $customName): static
    {
        $this->customName = $customName !== null && trim($customName) === '' ? null : $customName;

        return $this;
    }

    public function getDisplayName(): string
    {
        $custom = trim((string) ($this->customName ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $technical = trim((string) ($this->deviceName ?? ''));

        return $technical !== '' ? $technical : 'Appareil inconnu';
    }

    public function getDeviceType(): string
    {
        return $this->deviceType;
    }

    public function setDeviceType(string $deviceType): static
    {
        $this->deviceType = $deviceType;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(?\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

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

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;

        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
