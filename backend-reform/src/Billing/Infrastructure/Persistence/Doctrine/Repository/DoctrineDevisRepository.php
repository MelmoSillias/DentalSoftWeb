<?php

namespace App\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Billing\Domain\Model\Devis;
use App\Billing\Domain\Repository\DevisRepository;
use App\Billing\Domain\ValueObject\DevisId;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Devis as EntityDevis;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDevisRepository implements DevisRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Devis $devis): void
    {
        $id = $devis->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating devis via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityDevis::class, $id->toInt());
        if (!$entity instanceof EntityDevis) {
            throw new \RuntimeException(sprintf('Devis entity #%d not found for save.', $id->toInt()));
        }

        $entity->setStatut($devis->getStatus());
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(DevisId $id): ?Devis
    {
        $entity = $this->em->find(EntityDevis::class, $id->toInt());
        if (!$entity instanceof EntityDevis) {
            return null;
        }

        $status = (int) $entity->getStatut();
        if (!in_array($status, [Devis::STATUS_DRAFT, Devis::STATUS_VALIDATED, Devis::STATUS_CANCELLED], true)) {
            $status = Devis::STATUS_DRAFT;
        }

        return Devis::reconstitute(
            DevisId::fromInt((int) $entity->getId()),
            $status,
            $status === Devis::STATUS_CANCELLED,
        );
    }
}
