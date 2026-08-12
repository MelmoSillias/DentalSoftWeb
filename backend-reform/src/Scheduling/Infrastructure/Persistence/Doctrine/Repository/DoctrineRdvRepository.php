<?php

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Scheduling\Domain\Model\Rdv;
use App\Scheduling\Domain\Repository\RdvRepository;
use App\Scheduling\Domain\ValueObject\RdvId;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv as EntityRdv;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRdvRepository implements RdvRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Rdv $rdv): void
    {
        $id = $rdv->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating RDVs via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityRdv::class, $id->toInt());
        if (!$entity instanceof EntityRdv) {
            throw new \RuntimeException(sprintf('Rdv entity #%d not found for save.', $id->toInt()));
        }

        $entity->setStatut($rdv->getStatus());

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(RdvId $id): ?Rdv
    {
        $entity = $this->em->find(EntityRdv::class, $id->toInt());
        if (!$entity instanceof EntityRdv) {
            return null;
        }

        $patientId = $entity->getPatient()?->getId();
        $medecinId = $entity->getMedecin()?->getId();
        $status = (int) ($entity->getStatut() ?? Rdv::STATUS_PENDING);

        return Rdv::reconstitute(
            RdvId::fromInt((int) $entity->getId()),
            $status,
            $patientId,
            $medecinId,
        );
    }
}
