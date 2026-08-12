<?php

namespace App\Settings\Domain\Model;

use App\Settings\Domain\Exception\SettingsDomainException;
use App\Settings\Domain\ValueObject\AppSettingId;

final class AppSetting
{
    /**
     * @param array<string, mixed> $value
     */
    private function __construct(
        private ?AppSettingId $id,
        private string $keyName,
        private array $value,
    ) {
        if (trim($this->keyName) === '') {
            throw new SettingsDomainException('Setting keyName is required.');
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function reconstitute(AppSettingId $id, string $keyName, array $value): self
    {
        return new self($id, $keyName, $value);
    }

    /**
     * @param array<string, mixed> $value
     */
    public function replaceValue(array $value): void
    {
        $this->value = $value;
    }

    public function getId(): ?AppSettingId
    {
        return $this->id;
    }

    public function requireId(): AppSettingId
    {
        if ($this->id === null) {
            throw new SettingsDomainException('AppSetting id is not assigned.');
        }

        return $this->id;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /** @return array<string, mixed> */
    public function getValue(): array
    {
        return $this->value;
    }
}
