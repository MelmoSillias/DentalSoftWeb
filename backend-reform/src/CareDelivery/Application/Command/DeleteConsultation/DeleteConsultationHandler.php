<?php

namespace App\CareDelivery\Application\Command\DeleteConsultation;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\CareDelivery\Domain\Repository\ConsultationRepository;
use App\CareDelivery\Domain\ValueObject\ConsultationId;
use App\Shared\Application\Bus\CommandHandler;

final class DeleteConsultationHandler implements CommandHandler
{
    public function __construct(
        private readonly ConsultationRepository $consultationRepository,
        private readonly ConsultationWritePort $writePort,
    ) {
    }

    public function __invoke(DeleteConsultationCommand $command): bool
    {
        $consultation = $this->consultationRepository->findById(
            ConsultationId::fromInt($command->consultationId),
        );
        if ($consultation === null) {
            return false;
        }

        // Domain cancel applies to open consultations; legacy may still hard-delete closed ones.
        if ($consultation->isOpen()) {
            $consultation->cancel();
        }

        return $this->writePort->deleteConsultation($command->consultationId, $command->actor);
    }
}
