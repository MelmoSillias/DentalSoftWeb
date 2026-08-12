<?php

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Model\Consommable;
use App\Inventory\Domain\Repository\ConsommableRepository;
use App\Inventory\Domain\ValueObject\ConsommableId;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\Consommable as EntityConsommable;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineConsommableRepository implements ConsommableRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Consommable $consommable): void
    {
        $id = $consommable->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating consommables via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityConsommable::class, $id->toInt());
        if (!$entity instanceof EntityConsommable) {
            throw new \RuntimeException(sprintf('Consommable entity #%d not found for save.', $id->toInt()));
        }

        $entity->setNom($consommable->getNom());
        $entity->setQuantity($consommable->getQuantity());
        $entity->setLowValue($consommable->getLowValue());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(ConsommableId $id): ?Consommable
    {
        $entity = $this->em->find(EntityConsommable::class, $id->toInt());
        if (!$entity instanceof EntityConsommable) {
            return null;
        }

        return Consommable::reconstitute(
            ConsommableId::fromInt((int) $entity->getId()),
            (string) $entity->getNom(),
            (int) $entity->getQuantity(),
            (int) $entity->getLowValue(),
        );
    }
}
