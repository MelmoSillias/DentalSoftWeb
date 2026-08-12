<?php

namespace App\Billing\Domain\ValueObject;

use App\Billing\Domain\Exception\BillingDomainException;

/**
 * Persisted insurance claim status.
 *
 * Domain-enforced transitions:
 *  - pending → ready (markReady)
 *
 * Application / billing policy statuses (set outside this VO by workflows):
 *  - validated_empty — zero-amount claim auto-closed at cashdesk
 *  - rembourse — insurer refund applied via lot recovery
 *  - open — legacy synonym / derived when consultation not closed
 *  - in_lot — derived listing status when claim is assigned to a lot
 */
final class InsuranceStatus
{
    public const PENDING = 'pending';
    public const READY = 'ready';
    public const VALIDATED_EMPTY = 'validated_empty';
    public const REMBOURSE = 'rembourse';
    public const OPEN = 'open';

    /** @var list<string> */
    private const KNOWN = [
        self::PENDING,
        self::READY,
        self::VALIDATED_EMPTY,
        self::REMBOURSE,
        self::OPEN,
    ];

    private function __construct(private readonly string $value)
    {
        if (!in_array($this->value, self::KNOWN, true)) {
            throw new BillingDomainException(sprintf('Unknown insurance status "%s".', $this->value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function ready(): self
    {
        return new self(self::READY);
    }

    public function markReady(): self
    {
        if ($this->value === self::READY) {
            throw new BillingDomainException('Insurance claim is already ready.');
        }

        if (!in_array($this->value, [self::PENDING, self::OPEN], true)) {
            throw new BillingDomainException(sprintf(
                'Cannot mark insurance claim ready from status "%s".',
                $this->value,
            ));
        }

        return self::ready();
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isReady(): bool
    {
        return $this->value === self::READY;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
