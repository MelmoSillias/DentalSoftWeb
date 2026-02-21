<?php

namespace App\Notifier;

use App\Entity\Consultation;
use App\Entity\Patient;
use App\Entity\User;
use App\Enum\NotificationPriority;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class InAppNotification extends Notification
{
    private ?string $link = null;
    private ?NotificationPriority $priority = null;
    private ?User $emitter = null;
    private ?Patient $patient = null;
    private ?Consultation $consultation = null;
    private bool $deferFlush = false;

    public function __construct(string $subject)
    {
        parent::__construct($subject);
    }

    public function getChannels(RecipientInterface $recipient): array
    {
        return ['inapp'];
    }

    public function link(?string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function withPriority(NotificationPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAppPriority(): ?NotificationPriority
    {
        return $this->priority;
    }

    public function emitter(?User $emitter): self
    {
        $this->emitter = $emitter;

        return $this;
    }

    public function getEmitter(): ?User
    {
        return $this->emitter;
    }

    public function patient(?Patient $patient): self
    {
        $this->patient = $patient;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function consultation(?Consultation $consultation): self
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function deferFlush(bool $defer = true): self
    {
        $this->deferFlush = $defer;

        return $this;
    }

    public function shouldDeferFlush(): bool
    {
        return $this->deferFlush;
    }
}
