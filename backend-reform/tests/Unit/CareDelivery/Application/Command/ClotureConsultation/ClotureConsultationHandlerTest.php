<?php

namespace App\Tests\Unit\CareDelivery\Application\Command\ClotureConsultation;

use App\CareDelivery\Application\Command\ClotureConsultation\ClotureConsultationCommand;
use App\CareDelivery\Application\Command\ClotureConsultation\ClotureConsultationHandler;
use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\CareDelivery\Domain\Model\Consultation;
use App\CareDelivery\Domain\Repository\ConsultationRepository;
use App\CareDelivery\Domain\ValueObject\ConsultationId;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ClotureConsultationHandlerTest extends TestCase
{
    public function testAppliesDomainCloseThenDelegatesToWritePort(): void
    {
        $user = new stdClass();
        $payload = ['noteSeance' => 'done'];

        $consultation = Consultation::reconstitute(ConsultationId::fromInt(42), 10, 0, 5);

        $repo = $this->createMock(ConsultationRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (ConsultationId $id) => $id->toInt() === 42))
            ->willReturn($consultation);

        $writePort = $this->createMock(ConsultationWritePort::class);
        $writePort->expects(self::once())
            ->method('clotureConsultation')
            ->with(5, 42, $user, true, $payload);

        $handler = new ClotureConsultationHandler($repo, $writePort);
        $handler(new ClotureConsultationCommand(5, 42, $user, true, $payload));

        self::assertTrue($consultation->isClosed());
    }

    public function testRejectsClosedConsultationBeforePort(): void
    {
        $consultation = Consultation::reconstitute(ConsultationId::fromInt(42), 10, 1, 5);

        $repo = $this->createMock(ConsultationRepository::class);
        $repo->method('findById')->willReturn($consultation);

        $writePort = $this->createMock(ConsultationWritePort::class);
        $writePort->expects(self::never())->method('clotureConsultation');

        $handler = new ClotureConsultationHandler($repo, $writePort);

        $this->expectException(ConflictHttpException::class);

        $handler(new ClotureConsultationCommand(5, 42, null, false, []));
    }
}
