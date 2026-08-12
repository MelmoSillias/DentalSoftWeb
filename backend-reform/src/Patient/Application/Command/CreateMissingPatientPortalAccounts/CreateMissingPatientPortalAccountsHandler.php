<?php

namespace App\Patient\Application\Command\CreateMissingPatientPortalAccounts;

use App\Patient\Application\Port\PatientPortalPort;
use App\Shared\Application\Bus\CommandHandler;

final class CreateMissingPatientPortalAccountsHandler implements CommandHandler
{
    public function __construct(private readonly PatientPortalPort $portalPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateMissingPatientPortalAccountsCommand $command): array
    {
        return $this->portalPort->createMissingPatientPortalAccounts();
    }
}
