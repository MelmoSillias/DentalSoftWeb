<?php

namespace App\Tests\Reporting;

use App\Billing\Entity\Paiement;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Repository\ConsultationRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Reporting\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class DailyPayrollActivityTest extends TestCase
{
    private function queryBuilder(array $results): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($results);
        $builder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'distinct', 'leftJoin', 'innerJoin', 'where', 'andWhere', 'from', 'setParameter'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('getQuery')->willReturn($query);
        return $builder;
    }

    public function testActivityRevenueIncludesCrossDayPaymentsWithoutDoubleCounting(): void
    {
        $employee = (new Employe())->setNom('Test')->setPrenom('Journalier')->setTypeSalaire('pourcentage')->setValeurSalaire(40);
        (new \ReflectionProperty(Employe::class, 'id'))->setValue($employee, 7);
        $makeConsultation = static function (string $activityDate, string $paymentDate, float $amount) use ($employee): Consultation {
            $consultation = (new Consultation())->setMedecin($employee)->setCreatedAt(new \DateTimeImmutable($activityDate));
            $payment = (new Paiement())->setMontant($amount)->setDate(new \DateTimeImmutable($paymentDate))->setConsultation($consultation);
            $consultation->setPaiement($payment);
            return $consultation;
        };
        $sameDay = $makeConsultation('2026-10-01', '2026-10-01', 100);
        $free = $makeConsultation('2026-10-01', '2026-10-01', 0);
        $laterPayment = $makeConsultation('2026-10-03', '2026-10-05', 200);
        $olderConsultation = $makeConsultation('2026-09-20', '2026-10-06', 300);
        $consultations = $this->createMock(ConsultationRepository::class);
        $consultations->method('createQueryBuilder')->willReturn($this->queryBuilder([$sameDay, $free, $laterPayment]));
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            $this->queryBuilder([$sameDay->getPaiement(), $free->getPaiement(), $laterPayment->getPaiement(), $olderConsultation->getPaiement()]),
            $this->queryBuilder([]),
        );
        $service = (new \ReflectionClass(ReportService::class))->newInstanceWithoutConstructor();
        foreach (['em' => $manager, 'consultRepo' => $consultations, 'employeRepo' => $this->createMock(EmployeRepository::class)] as $property => $value) {
            (new \ReflectionProperty(ReportService::class, $property))->setValue($service, $value);
        }
        $result = $service->dailyPayrollActivities(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31 23:59:59'));
        self::assertSame(['2026-10-01', '2026-10-03', '2026-10-05', '2026-10-06'], array_column($result[7], 'day'));
        self::assertSame([100.0, 0.0, 200.0, 300.0], array_column($result[7], 'revenueTotal'));
        self::assertSame(2, $result[7][0]['consultations']);
        self::assertSame(0, $result[7][2]['consultations']);
    }
}
