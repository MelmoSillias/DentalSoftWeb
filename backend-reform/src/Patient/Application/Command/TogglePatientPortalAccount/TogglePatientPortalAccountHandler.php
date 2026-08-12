<?php

namespace App\Patient\Application\Command\TogglePatientPortalAccount;

use App\Patient\Application\Port\PatientPortalPort;
use App\Shared\Application\Bus\CommandHandler;

final class TogglePatientPortalAccountHandler implements CommandHandler
{
    public function __construct(private readonly PatientPortalPort $portalPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(TogglePatientPortalAccountCommand $command): array
    {
        return $this->portalPort->togglePatientPortalAccount($command->patientId, $command->active);
    }
}
