<?php

namespace App\Patient\Application\Command\CreatePatientPortalAccount;

use App\Patient\Application\Port\PatientPortalPort;
use App\Shared\Application\Bus\CommandHandler;

final class CreatePatientPortalAccountHandler implements CommandHandler
{
    public function __construct(private readonly PatientPortalPort $portalPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePatientPortalAccountCommand $command): array
    {
        return $this->portalPort->createPatientPortalAccount($command->patientId);
    }
}
