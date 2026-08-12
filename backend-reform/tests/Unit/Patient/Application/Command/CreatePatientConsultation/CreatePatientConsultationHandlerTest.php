<?php

namespace App\Tests\Unit\Patient\Application\Command\CreatePatientConsultation;

use App\Patient\Application\Command\CreatePatientConsultation\CreatePatientConsultationCommand;
use App\Patient\Application\Command\CreatePatientConsultation\CreatePatientConsultationHandler;
use App\Patient\Application\Port\CreateConsultationForPatientPort;
use App\Settings\Service\GlobalSettingsService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CreatePatientConsultationHandlerTest extends TestCase
{
    public function testRejectsInvalidPatientIdBeforePort(): void
    {
        $port = $this->createMock(CreateConsultationForPatientPort::class);
        $port->expects(self::never())->method('create');

        $settings = $this->createMock(GlobalSettingsService::class);
        $settings->method('isMedecinRequiredOnConsultationCreation')->willReturn(false);

        $handler = new CreatePatientConsultationHandler($port, $settings);
        $result = $handler(new CreatePatientConsultationCommand(0, [], null));

        self::assertSame([
            'error' => 'Consultation requires a valid patientId.',
            'status' => 400,
        ], $result);
    }

    public function testHappyPathCallsPort(): void
    {
        $payload = ['medecin_id' => 5, 'payant' => 0];
        $actor = new stdClass();
        $expected = [
            'success' => true,
            'status' => 200,
            'consultation_id' => 42,
            'paiement_id' => null,
        ];

        $port = $this->createMock(CreateConsultationForPatientPort::class);
        $port->expects(self::once())
            ->method('create')
            ->with(
                self::callback(static function (array $data): bool {
                    return ($data['patient_id'] ?? null) === 10
                        && ($data['medecin_id'] ?? null) === 5;
                }),
                $actor,
            )
            ->willReturn($expected);

        $settings = $this->createMock(GlobalSettingsService::class);
        $settings->expects(self::once())
            ->method('isMedecinRequiredOnConsultationCreation')
            ->willReturn(false);

        $handler = new CreatePatientConsultationHandler($port, $settings);
        $result = $handler(new CreatePatientConsultationCommand(10, $payload, $actor));

        self::assertSame($expected, $result);
    }

    public function testRejectsMissingMedecinWhenRequiredBeforePort(): void
    {
        $port = $this->createMock(CreateConsultationForPatientPort::class);
        $port->expects(self::never())->method('create');

        $settings = $this->createMock(GlobalSettingsService::class);
        $settings->method('isMedecinRequiredOnConsultationCreation')->willReturn(true);

        $handler = new CreatePatientConsultationHandler($port, $settings);
        $result = $handler(new CreatePatientConsultationCommand(10, [], null));

        self::assertSame([
            'error' => 'Consultation requires a medecin before save.',
            'status' => 400,
        ], $result);
    }
}
