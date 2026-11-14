<?php

namespace App\Tests\CareDelivery;

use App\CareDelivery\Application\Handler\GetOrdonnanceHandler;
use App\CareDelivery\Application\Mapper\OrdonnanceMapper;
use App\CareDelivery\Application\Query\GetOrdonnance;
use App\CareDelivery\Application\Service\ConsultationService;
use App\CareDelivery\Application\UseCase\SaveOrdonnance;
use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Domain\Entity\Ordonnance;
use App\CareDelivery\Domain\Entity\OrdonnanceLigne;
use App\Patient\Domain\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class OrdonnanceTest extends TestCase
{
    private EntityManagerInterface $em;
    private EntityRepository $repository;
    private GetOrdonnanceHandler $handler;
    private SaveOrdonnance $writer;
    private ConsultationService $facade;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->em->method('getRepository')->with(Ordonnance::class)->willReturn($this->repository);
        $mapper = new OrdonnanceMapper();
        $this->handler = new GetOrdonnanceHandler($this->em, $mapper);
        $this->writer = new SaveOrdonnance($this->em, $this->handler);

        // Exercise facade delegation without constructing unrelated legacy collaborators.
        $this->facade = (new \ReflectionClass(ConsultationService::class))->newInstanceWithoutConstructor();
        foreach (['getOrdonnance' => $this->handler, 'saveOrdonnance' => $this->writer, 'ordonnanceMapper' => $mapper] as $name => $service) {
            (new \ReflectionProperty(ConsultationService::class, $name))->setValue($this->facade, $service);
        }
    }

    public function testListAndDetailPreserveTheirDifferentResponseShapes(): void
    {
        $patient = (new Patient())->setNom('Doe')->setPrenom('Jane');
        $consultation = (new Consultation())->setPatient($patient);
        $this->setId($consultation, 12);
        $ordonnance = (new Ordonnance())->setDate(new \DateTime('2026-11-02 16:20:00'))
            ->setMedecinNom('Dr A')->setNote('After meals');
        $this->setId($ordonnance, 24);
        $consultation->addOrdonnance($ordonnance);
        $line = (new OrdonnanceLigne())->setDesignation('Medicine')->setPosologie('1 tablet')
            ->setFrequence('2/day')->setDuree('5 days')->setQuantite(10)->setInstructions('With water');
        $this->setId($line, 36);
        $ordonnance->addLigne($line);
        $this->repository->method('find')->with(24)->willReturn($ordonnance);
        $this->em->expects(self::never())->method('flush');
        $expectedLine = [
            'id' => 36, 'designation' => 'Medicine', 'posologie' => '1 tablet',
            'frequence' => '2/day', 'duree' => '5 days', 'quantite' => 10, 'instructions' => 'With water',
        ];
        $summary = ['id' => 24, 'date' => '2026-11-02', 'medecinNom' => 'Dr A', 'note' => 'After meals', 'lignes' => [$expectedLine]];
        self::assertSame([$summary], $this->facade->listOrdonnances($consultation));
        self::assertSame([
            'id' => 24, 'date' => '2026-11-02', 'medecinNom' => 'Dr A', 'note' => 'After meals',
            'consultationId' => 12, 'patient' => $patient->getFullName(), 'lignes' => [$expectedLine],
        ], $this->facade->getOrdonnanceData(24));
        self::assertSame([], $this->facade->listOrdonnances(new Consultation()));
    }

    public function testMissingPrescriptionReturnsNullBeforePayloadValidation(): void
    {
        $this->repository->method('find')->with(99)->willReturn(null);
        $this->em->expects(self::never())->method('flush');
        $this->em->expects(self::never())->method('persist');
        self::assertNull(($this->handler)(new GetOrdonnance(99)));
        self::assertNull($this->facade->updateOrdonnance(99, []));
    }

    /** @dataProvider invalidLines */
    public function testEmptyCreationDoesNotPersistOrFlush(array $payload): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        self::assertSame(['error' => 'Aucune ligne fournie'], $this->facade->createOrdonnance(new Consultation(), $payload));
    }

    /** @dataProvider invalidLines */
    public function testInvalidUpdateDoesNotMutateExistingPrescription(array $payload): void
    {
        $ordonnance = (new Ordonnance())->setNote('Keep');
        $line = new OrdonnanceLigne();
        $ordonnance->addLigne($line);
        $this->repository->method('find')->willReturn($ordonnance);
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('remove');
        $this->em->expects(self::never())->method('flush');
        try {
            $this->facade->updateOrdonnance(1, $payload + ['note' => 'Change']);
            self::fail('Expected invalid lines to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Au moins une ligne de prescription est requise.', $exception->getMessage());
            self::assertSame('Keep', $ordonnance->getNote());
            self::assertSame([$line], $ordonnance->getLignes()->toArray());
        }
    }

    public static function invalidLines(): array
    {
        return [[[]], [['lignes' => []]], [['lignes' => null]], [['lignes' => 'invalid']]];
    }

    public function testStagedCreationKeepsOwningConsultationAndDefersFlush(): void
    {
        $consultation = new Consultation();
        $persisted = [];
        $this->em->expects(self::exactly(3))->method('persist')->willReturnCallback(static function ($entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $this->em->expects(self::never())->method('flush');
        $ordonnance = $this->writer->stageCreation($consultation, [
            'date' => '2026-11-03', 'medecinNom' => 'Dr B', 'note' => 'Note',
            'lignes' => [['designation' => 'A', 'quantite' => '3'], []],
        ]);
        self::assertInstanceOf(Ordonnance::class, $ordonnance);
        self::assertSame($consultation, $ordonnance->getConsultation());
        self::assertSame('2026-11-03', $ordonnance->getDate()->format('Y-m-d'));
        self::assertSame('Dr B', $ordonnance->getMedecinNom());
        self::assertSame('Note', $ordonnance->getNote());
        self::assertSame([$ordonnance->getLignes()[0], $ordonnance->getLignes()[1], $ordonnance], $persisted);
        self::assertSame(3, $ordonnance->getLignes()[0]->getQuantite());
        self::assertSame('', $ordonnance->getLignes()[1]->getDesignation());
        self::assertNull($ordonnance->getLignes()[1]->getQuantite());
        self::assertSame($ordonnance, $ordonnance->getLignes()[0]->getOrdonnance());
        // Legacy creation sets only the owning side, not the in-memory inverse collection.
        self::assertCount(0, $consultation->getOrdonnances());
    }

    public function testCreateFlushesBeforeReturningGeneratedIdentifier(): void
    {
        $ordonnance = null;
        $this->em->method('persist')->willReturnCallback(static function ($entity) use (&$ordonnance): void {
            if ($entity instanceof Ordonnance) {
                $ordonnance = $entity;
            }
        });
        $this->em->expects(self::once())->method('flush')->willReturnCallback(function () use (&$ordonnance): void {
            $this->setId($ordonnance, 42);
        });
        self::assertSame(['success' => true, 'id' => 42], $this->facade->createOrdonnance(new Consultation(), ['lignes' => [[]]]));
    }

    public function testUpdateReplacesLinesClearsExplicitNullsAndPreservesOmittedFields(): void
    {
        $ordonnance = (new Ordonnance())->setDate(new \DateTime('2026-10-01'))->setMedecinNom('Dr A')->setNote('Old');
        $this->setId($ordonnance, 7);
        $old = new OrdonnanceLigne();
        $ordonnance->addLigne($old);
        $this->repository->expects(self::exactly(2))->method('find')->with(7)->willReturn($ordonnance);
        $this->em->expects(self::once())->method('remove')->with($old);
        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(OrdonnanceLigne::class));
        $this->em->expects(self::once())->method('flush');
        $result = $this->facade->updateOrdonnance(7, [
            'date' => '', 'note' => null, 'lignes' => ['ignored', ['designation' => 'New', 'quantite' => '2']],
        ]);
        self::assertSame('2026-10-01', $result['date']);
        self::assertSame('Dr A', $result['medecinNom']);
        self::assertNull($result['note']);
        self::assertNull($old->getOrdonnance());
        self::assertSame([1], array_keys($result['lignes']));
        self::assertSame('New', $result['lignes'][1]['designation']);
        self::assertSame(2, $result['lignes'][1]['quantite']);
        self::assertSame($ordonnance, $ordonnance->getLignes()->first()->getOrdonnance());
    }

    public function testUpdateAcceptsNonemptyListOfInvalidRowsAsBefore(): void
    {
        $ordonnance = (new Ordonnance())->setMedecinNom('Dr A')->setNote('Keep');
        $this->repository->method('find')->willReturn($ordonnance);
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::once())->method('flush');
        $result = $this->facade->updateOrdonnance(1, ['date' => '2026-11-04', 'medecinNom' => null, 'lignes' => [null, 'ignored']]);
        self::assertSame('2026-11-04', $result['date']);
        self::assertNull($result['medecinNom']);
        self::assertSame('Keep', $result['note']);
        self::assertSame([], $result['lignes']);
    }

    private function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
