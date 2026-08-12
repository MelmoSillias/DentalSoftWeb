<?php

namespace App\Communication\Infrastructure\Persistence\Doctrine\Entity;

use App\Communication\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'idx_notification_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_notification_date', columns: ['date_envoi'])]
#[ORM\Index(name: 'idx_notification_state', columns: ['etat_vu'])]
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const PRIORITY_INFO = 'info';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_CRITICAL = 'critical';
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DANGER = 'danger';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 255)]
    private ?string $type = self::TYPE_INFO;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(length: 255)]
    private ?string $etatVu = 'non_vu';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $emitter = null;

    #[ORM\Column(length: 20)]
    private ?string $priority = self::PRIORITY_INFO;

    public function getId(): ?int { return $this->id; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getPriority(): ?string { return $this->priority; }
    public function setPriority(string $priority): static { $this->priority = $priority; return $this; }
    public function getDateEnvoi(): ?\DateTimeInterface { return $this->dateEnvoi; }
    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static { $this->dateEnvoi = $dateEnvoi; return $this; }
    public function getEtatVu(): ?string { return $this->etatVu; }
    public function setEtatVu(string $etatVu): static { $this->etatVu = $etatVu; return $this; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getEmitter(): ?User { return $this->emitter; }
    public function setEmitter(?User $emitter): static { $this->emitter = $emitter; return $this; }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        if (null === $this->dateEnvoi) {
            $this->dateEnvoi = new \DateTimeImmutable();
        }
        if (null === $this->etatVu) {
            $this->etatVu = 'non_vu';
        }
        if (null === $this->priority) {
            $this->priority = self::PRIORITY_INFO;
        }
        if (null === $this->type) {
            $this->type = self::TYPE_INFO;
        }
    }
}