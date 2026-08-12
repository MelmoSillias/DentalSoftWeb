<?php

namespace App\Patient\Application\Command\CreatePatient;

final class CreatePatientCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?int $actorUserId = null,
    ) {
    }
}
