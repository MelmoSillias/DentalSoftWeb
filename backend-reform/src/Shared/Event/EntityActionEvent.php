<?php

namespace App\Shared\Event;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;

final class EntityActionEvent
{
    /**
     * @param list<string> $targetRoles
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly object $entity,
        private readonly string $actionType,
        private readonly array $targetRoles,
        private readonly ?User $emitter = null,
        private readonly array $context = [],
    ) {
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    /**
     * @return list<string>
     */
    public function getTargetRoles(): array
    {
        return $this->targetRoles;
    }

    public function getEmitter(): ?User
    {
        return $this->emitter;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}