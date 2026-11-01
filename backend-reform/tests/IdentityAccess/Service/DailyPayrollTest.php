<?php

namespace App\Tests\IdentityAccess\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\SalaryPaymentRepository;
use App\IdentityAccess\Service\PayrollService;
use App\Reporting\Service\ReportService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DailyPayrollTest extends TestCase
{
    private function setupPayroll(array $recorded = []): array
    {
        $employee = (new Employe())->setNom('Test')->setPrenom('Journalier')->setType('Medecin')
            ->setFrequencePaiement('journalier')->setTypeSalaire('pourcentage')->setValeurSalaire(40)
            ->setTypePrime('actes')->setValeurPrime(10);
        (new \ReflectionProperty(Employe::class, 'id'))->setValue($employee, 7);
        foreach ($recorded as $payment) $payment->setEmploye($employee);
        $employees = $this->createMock(EmployeRepository::class);
        $employees->method('find')->willReturn($employee);
        $employees->method('findAllEmployees')->willReturn([$employee]);
        $payments = $this->createMock(SalaryPaymentRepository::class);
        $payments->method('findBy')->willReturn($recorded);
        $methods = $this->createMock(ModeDePaiementRepository::class);
        $methods->method('find')->willReturn((new ModeDePaiement())->setLibelle('Espèces')->setType('cash'));
        $reports = $this->createMock(ReportService::class);
        $reports->method('dailyPayrollActivities')->willReturn([7 => [
            ['day' => '2026-10-02', 'revenueTotal' => 10000.0, 'consultations' => 2],
            ['day' => '2026-10-05', 'revenueTotal' => 20000.0, 'consultations' => 3],
            ['day' => '2026-10-06', 'revenueTotal' => 0.0, 'consultations' => 1],
        ]]);
        $reports->expects(self::never())->method('periodicDoctorReports');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('wrapInTransaction')->willReturnCallback(fn ($callback) => $callback());
        return [new PayrollService($manager, $employees, $payments, $methods, $reports), $employee, $manager];
    }

    private function recordedPayment(float $paid = 5000): SalaryPayment
    {
        return (new SalaryPayment())->setMonth(10)->setYear(2026)->setFrequenceSnapshot('journalier')
            ->setWorkedDay(new \DateTimeImmutable('2026-10-02'))->setSalaryTypeSnapshot('pourcentage')
            ->setSalaryValueSnapshot(40)->setBaseSalaryAmount(4000)->setPrimeTypeSnapshot('actes')
            ->setPrimeValueSnapshot(10)->setPrimeAmount(1000)->setCalculatedAmount(5000)->setPaidAmount($paid);
    }

    public function testAllActivityDaysHaveRevenueCalculationAndPaymentState(): void
    {
        [$service] = $this->setupPayroll([$this->recordedPayment()]);
        $context = $service->getDailyPaymentContext(7, 10, 2026);
        self::assertSame(3, $context['activityDays']);
        self::assertSame(1, $context['paidDays']);
        self::assertSame(15000.0, $context['calculatedAmount']);
        self::assertSame(10000.0, $context['breakdown']['remaining']);
        self::assertTrue($context['days'][0]['isPaid']);
        self::assertFalse($context['days'][0]['canPay']);
        self::assertSame(8000.0, $context['days'][1]['baseSalaryAmount']);
        self::assertSame(2000.0, $context['days'][1]['primeAmount']);
        self::assertTrue($context['days'][1]['canPay']);
        self::assertFalse($context['days'][2]['canPay']);
    }

    public function testFullDayPaymentHasNoDeductionsAndUsesAnEmployeeLock(): void
    {
        [$service, $employee, $manager] = $this->setupPayroll();
        $manager->expects(self::once())->method('lock')->with($employee, LockMode::PESSIMISTIC_WRITE);
        $result = $service->createSalaryPayment(['employeeId' => 7, 'month' => 10, 'year' => 2026, 'day' => '2026-10-02', 'paidAmount' => 5000, 'paymentMethodId' => 1]);
        self::assertSame(5000.0, $result['payment']['paidAmount']);
        self::assertSame(5000.0, $result['payment']['calculatedAmount']);
        self::assertSame(0.0, $result['payment']['inps']);
        self::assertSame(0.0, $result['payment']['indemniteSpeciale']);
        self::assertSame(10000.0, $result['payment']['baseAmount']);
    }

    /** @dataProvider invalidPayments */
    public function testInvalidDailyPaymentsAreRejected(array $overrides, string $message): void
    {
        [$service] = $this->setupPayroll();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $service->createSalaryPayment([...['employeeId' => 7, 'month' => 10, 'year' => 2026, 'day' => '2026-10-02', 'paidAmount' => 5000, 'paymentMethodId' => 1], ...$overrides]);
    }

    public static function invalidPayments(): array
    {
        return [
            'partial' => [['paidAmount' => 2000], 'integralement'],
            'overpayment' => [['paidAmount' => 5001], 'integralement'],
            'inps' => [['inps' => 100], 'INPS'],
            'indemnity' => [['indemniteSpeciale' => 100], 'indemnites'],
            'inactive day' => [['day' => '2026-10-03'], 'Aucune activite'],
            'invalid date' => [['day' => '2026-10-32'], 'Jour travaille invalide'],
        ];
    }

    public function testExistingPartialPaymentAlsoPreventsASecondPayment(): void
    {
        [$service] = $this->setupPayroll([$this->recordedPayment(1000)]);
        $context = $service->getDailyPaymentContext(7, 10, 2026);
        self::assertSame(0, $context['paidDays']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('paiement existe deja');
        $service->createSalaryPayment(['employeeId' => 7, 'month' => 10, 'year' => 2026, 'day' => '2026-10-02', 'paidAmount' => 4000]);
    }

    public function testEmployeeViewSummarizesTheEntireMonthForDailyWorkers(): void
    {
        [$service] = $this->setupPayroll([$this->recordedPayment()]);
        $rows = $service->listEmployeePayrolls(10, 2026);
        self::assertSame(1, $rows[0]['frequencyOrder']);
        self::assertSame(1, $rows[0]['context']['paidDays']);
        self::assertSame(3, $rows[0]['context']['activityDays']);
        self::assertSame(15000.0, $rows[0]['context']['calculatedAmount']);
    }
}
