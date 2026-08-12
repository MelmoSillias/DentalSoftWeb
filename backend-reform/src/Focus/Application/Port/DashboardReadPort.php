<?php

namespace App\Focus\Application\Port;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use DateTimeImmutable;

interface DashboardReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getAdminCards(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getAdminCarousels(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getAdminTabs(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getMedecinCards(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getMedecinCarousels(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getMedecinTabs(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getReceptionCards(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getReceptionCarousels(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getReceptionTabs(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
