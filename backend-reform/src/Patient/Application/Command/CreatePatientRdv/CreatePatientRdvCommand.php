<?php

namespace App\Patient\Application\Command\CreatePatientRdv;

final class CreatePatientRdvCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?object $actor = null,
    ) {
    }
}
