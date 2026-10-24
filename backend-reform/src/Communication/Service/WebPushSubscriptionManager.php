<?php

namespace App\Communication\Service;

use App\Communication\Entity\WebPushSubscription;
use App\Communication\Repository\WebPushSubscriptionRepository;
use App\IdentityAccess\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class WebPushSubscriptionManager
{
    public function __construct(
        private readonly WebPushSubscriptionRepository $repository,
        private readonly WebPushSubscriptionValidator $validator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(User $user, array $payload, ?string $userAgent): void
    {
        $subscription = $this->validator->validate($payload);
        $existing = $this->repository->findOneByEndpoint($subscription['endpoint']);
        $entity = $existing ?? new WebPushSubscription();

        $entity
            ->setUser($user)
            ->setEndpoint($subscription['endpoint'])
            ->setP256dh($subscription['p256dh'])
            ->setAuth($subscription['auth'])
            ->setContentEncoding($subscription['contentEncoding'])
            ->setUserAgent($this->truncateUserAgent($userAgent))
            ->touch();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function deleteOwned(User $user, string $endpoint): bool
    {
        if (!$this->validator->isAllowedEndpoint($endpoint)) {
            return false;
        }

        $existing = $this->repository->findOneByEndpoint($endpoint);
        if (!$existing instanceof WebPushSubscription || $existing->getUser()?->getId() !== $user->getId()) {
            return false;
        }

        $this->entityManager->remove($existing);
        $this->entityManager->flush();

        return true;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}
