<?php

namespace App\Communication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'showcase_message')]
class ShowcaseMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 64, unique: true)]
    public string $externalId;
    #[ORM\Column(length: 255)]
    public string $sender;
    #[ORM\Column(length: 500)]
    public string $subject;
    #[ORM\Column(type: 'text')]
    public string $body;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $receivedAt;
    #[ORM\Column(length: 20)]
    public string $status = 'new';
    #[ORM\Column(type: 'json')]
    public array $replies = [];

    public function toArray(): array
    {
        return ['id' => $this->id, 'sender' => $this->sender, 'subject' => $this->subject,
            'body' => $this->body, 'receivedAt' => $this->receivedAt->format(DATE_ATOM),
            'status' => $this->status, 'replies' => $this->replies];
    }
}
