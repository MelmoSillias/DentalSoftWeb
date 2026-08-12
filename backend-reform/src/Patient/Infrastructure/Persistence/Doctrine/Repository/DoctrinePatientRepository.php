<?php

namespace App\Patient\Infrastructure\Persistence\Doctrine\Repository;

use App\Patient\Domain\Model\Patient;
use App\Patient\Domain\Repository\PatientRepository;
use App\Patient\Domain\ValueObject\PatientId;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient as EntityPatient;
use App\Patient\Infrastructure\Persistence\Doctrine\Mapper\PatientMapper;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePatientRepository implements PatientRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PatientMapper $mapper,
    ) {
    }

    public function save(Patient $patient): void
    {
        $id = $patient->getId();
        $isNew = $id === null;

        if ($isNew) {
            $entity = new EntityPatient();
        } else {
            $entity = $this->em->find(EntityPatient::class, $id->toInt());
            if (!$entity instanceof EntityPatient) {
                throw new \RuntimeException(sprintf('Patient entity #%d not found for save.', $id->toInt()));
            }
        }

        $this->mapper->applyDomain($patient, $entity);
        $this->em->persist($entity);
        $this->em->flush();

        if ($isNew) {
            $generatedId = $entity->getId();
            if ($generatedId === null) {
                throw new \RuntimeException('Patient id was not generated after flush.');
            }
            $patient->assignId(PatientId::fromInt($generatedId));
        }

        $this->mapper->assignGeneratedChildIds($patient, $entity);
    }

    public function findById(PatientId $id): ?Patient
    {
        $entity = $this->em->find(EntityPatient::class, $id->toInt());

        return $entity instanceof EntityPatient ? $this->mapper->toDomain($entity) : null;
    }

    public function findActiveById(PatientId $id): ?Patient
    {
        $patient = $this->findById($id);
        if ($patient === null || $patient->isDeleted()) {
            return null;
        }

        return $patient;
    }

    public function findDeletedById(PatientId $id): ?Patient
    {
        $patient = $this->findById($id);
        if ($patient === null || !$patient->isDeleted()) {
            return null;
        }

        return $patient;
    }
}
