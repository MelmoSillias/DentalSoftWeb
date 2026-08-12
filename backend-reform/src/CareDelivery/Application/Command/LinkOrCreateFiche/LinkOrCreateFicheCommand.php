<?php

namespace App\CareDelivery\Application\Command\LinkOrCreateFiche;

final class LinkOrCreateFicheCommand
{
    public function __construct(
        public readonly int $consultationId,
        public readonly ?int $ficheId = null,
        public readonly ?object $user = null,
        public readonly bool $restrictToMedecin = false,
        public readonly bool $forceCreate = false,
        public readonly bool $allowDuplicate = false,
    ) {
    }
}
