<?php

namespace App\Focus\Domain\Model;

use App\Focus\Domain\Exception\FocusDomainException;
use App\Focus\Domain\ValueObject\FocusSnapshotId;
use DateTimeImmutable;

/**
 * Read-model domain object describing a dashboard snapshot request.
 */
final class FocusSnapshot
{
    private const ALLOWED_ROLES = ['admin', 'medecin', 'reception'];
    private const ALLOWED_TYPES = ['cards', 'carousels', 'tabs'];

    private function __construct(
        private FocusSnapshotId $id,
        private string $role,
        private string $type,
        private DateTimeImmutable $from,
        private DateTimeImmutable $to,
        private ?int $medecinId,
    ) {
        if (!in_array($this->role, self::ALLOWED_ROLES, true)) {
            throw new FocusDomainException(sprintf('Unsupported dashboard role "%s".', $this->role));
        }
        if (!in_array($this->type, self::ALLOWED_TYPES, true)) {
            throw new FocusDomainException(sprintf('Unsupported dashboard type "%s".', $this->type));
        }
        if ($this->from > $this->to) {
            throw new FocusDomainException('Focus snapshot range is invalid (from > to).');
        }
    }

    public static function create(
        string $role,
        string $type,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?int $medecinId = null,
    ): self {
        $id = FocusSnapshotId::fromString(sprintf(
            '%s:%s:%s:%s:%s',
            $role,
            $type,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $medecinId ?? 'none'
        ));

        return new self($id, strtolower($role), strtolower($type), $from, $to, $medecinId);
    }

    public function getId(): FocusSnapshotId
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFrom(): DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): DateTimeImmutable
    {
        return $this->to;
    }

    public function getMedecinId(): ?int
    {
        return $this->medecinId;
    }
}
