<?php

namespace App\Communication\Entity;

use App\Communication\Repository\WebPushSubscriptionRepository;
use App\IdentityAccess\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebPushSubscriptionRepository::class)]
#[ORM\Table(name: 'web_push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_web_push_endpoint_hash', columns: ['endpoint_hash'])]
#[ORM\Index(name: 'idx_web_push_user', columns: ['user_id'])]
class WebPushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $endpoint = '';

    #[ORM\Column(length: 64)]
    private string $endpointHash = '';

    #[ORM\Column(length: 255)]
    private string $p256dh = '';

    #[ORM\Column(name: 'auth_token', length: 255)]
    private string $auth = '';

    #[ORM\Column(length: 32)]
    private string $contentEncoding = 'aes128gcm';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);

        return $this;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function setP256dh(string $p256dh): static
    {
        $this->p256dh = $p256dh;

        return $this;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function setAuth(string $auth): static
    {
        $this->auth = $auth;

        return $this;
    }

    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }

    public function setContentEncoding(string $contentEncoding): static
    {
        $this->contentEncoding = $contentEncoding;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(): static
    {
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }
}
