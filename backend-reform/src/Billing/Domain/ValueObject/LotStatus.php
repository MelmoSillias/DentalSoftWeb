<?php

namespace App\Billing\Domain\ValueObject;

use App\Billing\Domain\Exception\BillingDomainException;

/**
 * Persisted insurance lot status.
 *
 * Domain-enforced transitions:
 *  - ouvert → envoye (send)
 *  - envoye → ouvert (reopen)
 *  - envoye → confirme (confirm)
 *  - confirme → envoye (unconfirm)
 *
 * Refund statuses (partiellement_rembourse / rembourse) are application policy
 * driven by payment amounts and may be reconstituted without transition.
 */
final class LotStatus
{
    public const OUVERT = 'ouvert';
    public const ENVOYE = 'envoye';
    public const CONFIRME = 'confirme';
    public const PARTIELLEMENT_REMBOURSE = 'partiellement_rembourse';
    public const REMBOURSE = 'rembourse';

    /** Legacy synonym mapped to rembourse */
    public const LEGACY_RECOUVRE = 'recouvre';

    /** @var list<string> */
    private const KNOWN = [
        self::OUVERT,
        self::ENVOYE,
        self::CONFIRME,
        self::PARTIELLEMENT_REMBOURSE,
        self::REMBOURSE,
    ];

    private function __construct(private readonly string $value)
    {
        if (!in_array($this->value, self::KNOWN, true)) {
            throw new BillingDomainException(sprintf('Unknown lot status "%s".', $this->value));
        }
    }

    public static function fromString(string $value): self
    {
        if ($value === self::LEGACY_RECOUVRE) {
            $value = self::REMBOURSE;
        }

        return new self($value);
    }

    public static function ouvert(): self
    {
        return new self(self::OUVERT);
    }

    public function send(): self
    {
        if ($this->value !== self::OUVERT) {
            throw new BillingDomainException(sprintf(
                'Cannot send lot from status "%s".',
                $this->value,
            ));
        }

        return new self(self::ENVOYE);
    }

    public function reopen(): self
    {
        if ($this->value !== self::ENVOYE) {
            throw new BillingDomainException(sprintf(
                'Cannot reopen lot from status "%s".',
                $this->value,
            ));
        }

        return new self(self::OUVERT);
    }

    public function confirm(): self
    {
        if ($this->value !== self::ENVOYE) {
            throw new BillingDomainException(sprintf(
                'Cannot confirm lot from status "%s".',
                $this->value,
            ));
        }

        return new self(self::CONFIRME);
    }

    public function unconfirm(): self
    {
        if ($this->value !== self::CONFIRME) {
            throw new BillingDomainException(sprintf(
                'Cannot unconfirm lot from status "%s".',
                $this->value,
            ));
        }

        return new self(self::ENVOYE);
    }

    public function isOuvert(): bool
    {
        return $this->value === self::OUVERT;
    }

    public function isEnvoye(): bool
    {
        return $this->value === self::ENVOYE;
    }

    public function isConfirme(): bool
    {
        return $this->value === self::CONFIRME;
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
