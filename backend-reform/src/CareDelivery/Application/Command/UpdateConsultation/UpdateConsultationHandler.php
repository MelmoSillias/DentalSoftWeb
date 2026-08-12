<?php

namespace App\CareDelivery\Application\Command\UpdateConsultation;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\Exception\ConsultationNotFoundException;
use App\CareDelivery\Domain\Repository\ConsultationRepository;
use App\CareDelivery\Domain\ValueObject\ConsultationId;
use App\Shared\Application\Bus\CommandHandler;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateConsultationHandler implements CommandHandler
{
    public function __construct(
        private readonly ConsultationRepository $consultationRepository,
        private readonly ConsultationWritePort $writePort,
    ) {
    }

    public function __invoke(UpdateConsultationCommand $command): void
    {
        $consultation = $this->consultationRepository->findById(
            ConsultationId::fromInt($command->consultationId),
        );
        if ($consultation === null) {
            throw new NotFoundHttpException(
                ConsultationNotFoundException::withId($command->consultationId)->getMessage(),
            );
        }

        try {
            $consultation->assertOpen();
        } catch (CareDeliveryDomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        $this->writePort->updateConsultation(
            $command->ficheId,
            $command->consultationId,
            $command->data,
            $command->user,
            $command->restrictToMedecin,
        );
    }
}
