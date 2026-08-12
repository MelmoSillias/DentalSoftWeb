<?php

namespace App\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\Exception\UserAlreadyDisabledException;
use App\IdentityAccess\Domain\ValueObject\UserId;

final class User
{
    private const USERNAME_MAX_LENGTH = 180;

    /**
     * @param list<string> $roles
     */
    private function __construct(
        private ?UserId $id,
        private string $username,
        private array $roles,
        private bool $active,
        private bool $notificationsEnabled,
    ) {
        $this->username = self::assertValidUsername($username);
    }

    /**
     * @param list<string> $roles
     */
    public static function reconstitute(
        UserId $id,
        string $username,
        array $roles,
        bool $active,
        bool $notificationsEnabled,
    ): self {
        return new self($id, $username, $roles, $active, $notificationsEnabled);
    }

    /**
     * @param list<string> $roles
     */
    public static function create(string $username, array $roles = []): self
    {
        return new self(null, $username, $roles, true, true);
    }

    public function softDisable(): void
    {
        if (!$this->active) {
            throw UserAlreadyDisabledException::withId($this->requireId()->toInt());
        }
        $this->active = false;
    }

    public function enable(): void
    {
        $this->active = true;
    }

    public function updateProfile(string $username, ?bool $notificationsEnabled = null): void
    {
        $this->assertActive();
        $this->username = self::assertValidUsername($username);
        if ($notificationsEnabled !== null) {
            $this->notificationsEnabled = $notificationsEnabled;
        }
    }

    public static function assertValidUsername(string $username): string
    {
        $username = trim($username);
        if ($username === '') {
            throw new IdentityAccessDomainException('Username is required.');
        }
        if (mb_strlen($username) > self::USERNAME_MAX_LENGTH) {
            throw new IdentityAccessDomainException(sprintf(
                'Username must be at most %d characters.',
                self::USERNAME_MAX_LENGTH,
            ));
        }

        return $username;
    }

    public static function assertValidEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);
        if ($email === '') {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new IdentityAccessDomainException('Email is invalid.');
        }

        return $email;
    }

    public function assignId(UserId $id): void
    {
        if ($this->id !== null) {
            throw new IdentityAccessDomainException('User already has an id.');
        }
        $this->id = $id;
    }

    public function getId(): ?UserId
    {
        return $this->id;
    }

    public function requireId(): UserId
    {
        if ($this->id === null) {
            throw new IdentityAccessDomainException('User id is not assigned.');
        }

        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isNotificationsEnabled(): bool
    {
        return $this->notificationsEnabled;
    }

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new IdentityAccessDomainException('User is disabled.');
        }
    }
}
