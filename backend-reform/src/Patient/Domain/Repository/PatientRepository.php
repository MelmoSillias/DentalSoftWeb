<?php

namespace App\Patient\Domain\Repository;

use App\Patient\Domain\Model\Patient;
use App\Patient\Domain\ValueObject\PatientId;

interface PatientRepository
{
    public function save(Patient $patient): void;

    public function findById(PatientId $id): ?Patient;

    public function findActiveById(PatientId $id): ?Patient;

    public function findDeletedById(PatientId $id): ?Patient;
}
