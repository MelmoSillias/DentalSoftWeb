<?php

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Domain\Model\User;
use App\IdentityAccess\Domain\Repository\UserRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User as EntityUser;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(User $user): void
    {
        $id = $user->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating users via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityUser::class, $id->toInt());
        if (!$entity instanceof EntityUser) {
            throw new \RuntimeException(sprintf('User entity #%d not found for save.', $id->toInt()));
        }

        $entity->setUsername($user->getUsername());
        $entity->setRoles($user->getRoles());
        $entity->setActive($user->isActive());
        $entity->setNotificationsEnabled($user->isNotificationsEnabled());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->em->find(EntityUser::class, $id->toInt());
        if (!$entity instanceof EntityUser) {
            return null;
        }

        return User::reconstitute(
            UserId::fromInt((int) $entity->getId()),
            (string) $entity->getUsername(),
            array_values(array_filter(
                $entity->getRoles(),
                static fn (string $role): bool => $role !== 'ROLE_USER'
            )),
            $entity->isActive(),
            $entity->isNotificationsEnabled(),
        );
    }

    public function findActiveById(UserId $id): ?User
    {
        $user = $this->findById($id);
        if ($user === null || !$user->isActive()) {
            return null;
        }

        return $user;
    }
}
