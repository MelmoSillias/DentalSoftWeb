<?php

namespace App\Patient\Application\Command\CreatePatient;

use App\Patient\Application\Port\PatientCachePort;
use App\Patient\Application\Port\PatientCreatedSideEffectsPort;
use App\Patient\Application\Port\PatientInsurancePort;
use App\Patient\Application\Port\PatientRealtimePort;
use App\Patient\Domain\Exception\PatientDomainException;
use App\Patient\Domain\Model\Patient;
use App\Patient\Domain\Repository\PatientRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TransactionManager;
use InvalidArgumentException;
use Throwable;

final class CreatePatientHandler implements CommandHandler
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PatientInsurancePort $insurancePort,
        private readonly PatientCreatedSideEffectsPort $sideEffectsPort,
        private readonly PatientCachePort $cachePort,
        private readonly PatientRealtimePort $realtimePort,
        private readonly Clock $clock,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreatePatientCommand $command): array
    {
        $data = self::normalizeSmsPreferenceKeys($command->data);

        if (!isset($data['nom'], $data['prenom'], $data['sexe'], $data['telephone'])) {
            return ['error' => 'Paramètres obligatoires manquants', 'status' => 400];
        }

        try {
            $patientId = $this->transactionManager->transactional(function () use ($data): int {
                $patient = Patient::create($data, $this->clock->now());
                $this->patientRepository->save($patient);

                $patientId = $patient->requireId()->toInt();
                $this->insurancePort->applyInsuranceProfile($patientId, $data);

                return $patientId;
            });

            $portalAccount = $this->sideEffectsPort->afterCreate($patientId, $command->actorUserId);
            $this->cachePort->clearPatientsCache();
            $this->realtimePort->publishPatientRefresh($patientId, 'created');

            return [
                'success' => true,
                'status' => 201,
                'patientId' => $patientId,
                'portalAccount' => $portalAccount,
            ];
        } catch (PatientDomainException|InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeSmsPreferenceKeys(array $data): array
    {
        $source = isset($data['smsPreferences']) && is_array($data['smsPreferences'])
            ? $data['smsPreferences']
            : $data;

        $aliases = [
            'patientCreated' => 'smsPatientCreated',
            'receipt' => 'smsReceipt',
            'ticket' => 'smsTicket',
            'invoice' => 'smsInvoice',
            'appointmentReminder' => 'smsAppointmentReminder',
            'unsubscribed' => 'smsUnsubscribed',
            'blacklisted' => 'smsBlacklisted',
        ];

        foreach ($aliases as $short => $canonical) {
            if (array_key_exists($short, $source) && !array_key_exists($canonical, $source)) {
                $source[$canonical] = $source[$short];
            }
        }

        $data['smsPreferences'] = $source;

        return $data;
    }
}
