<?php

namespace App\Patient\Application\Command\CreatePatientConsultation;

use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\Model\Consultation;
use App\Patient\Application\Port\CreateConsultationForPatientPort;
use App\Settings\Service\GlobalSettingsService;
use App\Shared\Application\Bus\CommandHandler;

final class CreatePatientConsultationHandler implements CommandHandler
{
    public function __construct(
        private readonly CreateConsultationForPatientPort $createConsultationPort,
        private readonly GlobalSettingsService $globalSettingsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePatientConsultationCommand $command): array
    {
        $payload = $command->payload;
        $payload['patient_id'] = $command->patientId;

        $medecinId = !empty($payload['medecin_id']) ? (int) $payload['medecin_id'] : null;

        try {
            // Domain validation; legacy port still owns full persist + side effects.
            $consultation = Consultation::create($command->patientId, $medecinId);
            if ($this->globalSettingsService->isMedecinRequiredOnConsultationCreation()) {
                $consultation->requireMedecinForSave();
            }
        } catch (CareDeliveryDomainException $e) {
            return [
                'error' => $e->getMessage(),
                'status' => 400,
            ];
        }

        return $this->createConsultationPort->create($payload, $command->actor);
    }
}
