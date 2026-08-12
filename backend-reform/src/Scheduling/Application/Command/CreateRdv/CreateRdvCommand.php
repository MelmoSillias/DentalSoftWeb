<?php

namespace App\Scheduling\Application\Command\CreateRdv;

final class CreateRdvCommand
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
