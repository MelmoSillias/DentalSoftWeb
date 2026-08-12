<?php

namespace App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalRecord\Domain\Model\FicheMedicale;
use App\ClinicalRecord\Domain\Repository\FicheMedicaleRepository;
use App\ClinicalRecord\Domain\ValueObject\FicheMedicaleId;
use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\FicheMedicale as EntityFicheMedicale;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFicheMedicaleRepository implements FicheMedicaleRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(FicheMedicale $fiche): void
    {
        // Strangler stub: persistence of archive flag will be wired when Domain owns that column.
        $id = $fiche->getId();
        if ($id === null) {
            throw new \RuntimeException('Creating fiches via Domain repository is not supported in this strangler slice.');
        }

        $entity = $this->em->find(EntityFicheMedicale::class, $id->toInt());
        if (!$entity instanceof EntityFicheMedicale) {
            throw new \RuntimeException(sprintf('FicheMedicale entity #%d not found for save.', $id->toInt()));
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(FicheMedicaleId $id): ?FicheMedicale
    {
        $entity = $this->em->find(EntityFicheMedicale::class, $id->toInt());
        if (!$entity instanceof EntityFicheMedicale) {
            return null;
        }

        $patientId = method_exists($entity, 'getPatient') && $entity->getPatient()
            ? (int) $entity->getPatient()->getId()
            : 0;
        if ($patientId <= 0) {
            return null;
        }

        return FicheMedicale::reconstitute(FicheMedicaleId::fromInt((int) $entity->getId()), $patientId, false);
    }
}
