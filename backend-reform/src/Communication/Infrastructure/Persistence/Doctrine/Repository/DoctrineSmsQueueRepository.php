<?php

namespace App\Communication\Infrastructure\Persistence\Doctrine\Repository;

use App\Communication\Domain\Model\SmsQueueItem;
use App\Communication\Domain\Repository\SmsQueueRepository;
use App\Communication\Domain\ValueObject\SmsQueueId;
use App\Communication\Infrastructure\Persistence\Doctrine\Entity\SmsQueue as EntitySmsQueue;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSmsQueueRepository implements SmsQueueRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(SmsQueueItem $item): void
    {
        $id = $item->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating SMS queue items via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntitySmsQueue::class, $id->toInt());
        if (!$entity instanceof EntitySmsQueue) {
            throw new \RuntimeException(sprintf('SmsQueue entity #%d not found for save.', $id->toInt()));
        }

        if (method_exists($entity, 'setStatus')) {
            $entity->setStatus($item->getStatus());
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(SmsQueueId $id): ?SmsQueueItem
    {
        $entity = $this->em->find(EntitySmsQueue::class, $id->toInt());
        if (!$entity instanceof EntitySmsQueue) {
            return null;
        }

        $phone = method_exists($entity, 'getPhone') ? (string) $entity->getPhone() : '';
        $message = method_exists($entity, 'getMessage') ? (string) $entity->getMessage() : '';
        $status = method_exists($entity, 'getStatus') ? (string) $entity->getStatus() : 'pending';

        return SmsQueueItem::reconstitute(
            SmsQueueId::fromInt((int) $entity->getId()),
            $phone,
            $message,
            $status,
        );
    }
}
