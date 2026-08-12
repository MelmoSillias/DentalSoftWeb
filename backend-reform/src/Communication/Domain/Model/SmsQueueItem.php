<?php

namespace App\Communication\Domain\Model;

use App\Communication\Domain\Exception\CommunicationDomainException;
use App\Communication\Domain\ValueObject\SmsQueueId;

final class SmsQueueItem
{
    private function __construct(
        private ?SmsQueueId $id,
        private string $phone,
        private string $message,
        private string $status,
    ) {
        if (trim($this->phone) === '' || trim($this->message) === '') {
            throw new CommunicationDomainException('SmsQueueItem requires phone and message.');
        }
    }

    public static function reconstitute(SmsQueueId $id, string $phone, string $message, string $status): self
    {
        return new self($id, $phone, $message, $status);
    }

    public function markCancelled(): void
    {
        if ($this->status === 'cancelled') {
            throw new CommunicationDomainException('Sms queue item is already cancelled.');
        }
        if ($this->status === 'sent') {
            throw new CommunicationDomainException('Cannot cancel a sent SMS.');
        }
        $this->status = 'cancelled';
    }

    public function getId(): ?SmsQueueId
    {
        return $this->id;
    }

    public function requireId(): SmsQueueId
    {
        if ($this->id === null) {
            throw new CommunicationDomainException('SmsQueueItem id is not assigned.');
        }

        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
