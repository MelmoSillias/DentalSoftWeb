<?php

namespace App\Dto\Focus;

final class FocusReceptionPatientDto
{
    public function __construct(
        private int $id,
        private string $nom,
        private string $prenom,
        private string $fullname,
        private ?string $telephone,
        private ?string $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'fullname' => $this->fullname,
            'telephone' => $this->telephone,
            'createdAt' => $this->createdAt,
        ];
    }
}