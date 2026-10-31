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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PayrollPeriodTest extends TestCase
{
    private function employee(): Employe
    {
        $employee = (new Employe())->setNom('Test')->setPrenom('Paie')->setType('Medecin')
            ->setFrequencePaiement('mensuel')->setTypeSalaire('pourcentage')->setValeurSalaire(50)
            ->setTypePrime('actes')->setValeurPrime(10);
        (new \ReflectionProperty(Employe::class, 'id'))->setValue($employee, 7);
        return $employee;
    }

    private function firstPayment(Employe $employee): SalaryPayment
    {
        return (new SalaryPayment())->setEmploye($employee)->setMonth(10)->setYear(2026)
            ->setPeriodStart(new \DateTimeImmutable('2026-10-01'))->setPeriodEnd(new \DateTimeImmutable('2026-10-31'))
            ->setSalaryTypeSnapshot('fixe')->setSalaryValueSnapshot(100000)->setBaseSalaryAmount(100000)
            ->setPrimeTypeSnapshot('fixe')->setPrimeValueSnapshot(10000)->setPrimeAmount(10000)
            ->setIndemniteSpeciale(5000)->setInps(3000)->setCalculatedAmount(112000)->setPaidAmount(30000);
    }

    private function service(Employe $employee, ?SalaryPayment $first, float $alreadyPaid): array
    {
        $employees = $this->createMock(EmployeRepository::class);
        $employees->method('find')->with(7)->willReturn($employee);
        $employees->method('findAllEmployees')->willReturn([$employee]);
        $payments = $this->createMock(SalaryPaymentRepository::class);
        $payments->method('findFirstForPeriod')->with($employee, 10, 2026, null)->willReturn($first);
        $payments->method('sumPaidForMonthlyPeriod')->willReturn($alreadyPaid);
        $methods = $this->createMock(ModeDePaiementRepository::class);
        $methods->method('find')->willReturn((new ModeDePaiement())->setLibelle('Espèces')->setType('cash'));
        $reports = $this->createMock(ReportService::class);
        if ($first) $reports->expects(self::never())->method('periodicDoctorReports');
        $manager = $this->createMock(EntityManagerInterface::class);
        return [new PayrollService($manager, $employees, $payments, $methods, $reports), $payments, $reports, $manager];
    }

    public function testExistingPaymentPreservesOriginalCalculationAfterEmployeeSettingsChange(): void
    {
        $employee = $this->employee();
        [$service] = $this->service($employee, $this->firstPayment($employee), 50000);
        $context = $service->getPaymentContext(7, 10, 2026);
        self::assertTrue($context['salaryLocked']);
        self::assertSame('fixe', $context['employee']['typeSalaire']);
        self::assertSame('fixe', $context['employee']['typePrime']);
        self::assertSame(5000.0, $context['indemniteSpeciale']);
        self::assertSame(3000.0, $context['inps']);
        self::assertSame(112000.0, $context['calculatedAmount']);
        self::assertSame(62000.0, $context['breakdown']['remaining']);
    }

    public function testNextPaymentReusesAllSavedSalaryComponents(): void
    {
        $employee = $this->employee();
        [$service, , , $manager] = $this->service($employee, $this->firstPayment($employee), 30000);
        $manager->expects(self::once())->method('flush');
        $result = $service->createSalaryPayment([
            'employeeId' => 7, 'month' => 10, 'year' => 2026, 'paidAmount' => 20000, 'paymentMethodId' => 1,
            'primeAmount' => 99999, 'indemniteSpeciale' => 99999, 'inps' => 0,
        ]);
        self::assertSame(112000.0, $result['payment']['calculatedAmount']);
        self::assertSame(10000.0, $result['payment']['primeAmount']);
        self::assertSame(5000.0, $result['payment']['indemniteSpeciale']);
        self::assertSame(3000.0, $result['payment']['inps']);
        self::assertSame(20000.0, $result['payment']['paidAmount']);
    }

    public function testCannotPayMoreThanFrozenBalance(): void
    {
        $employee = $this->employee();
        [$service] = $this->service($employee, $this->firstPayment($employee), 100000);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('depasse le reste a payer');
        $service->createSalaryPayment(['employeeId' => 7, 'month' => 10, 'year' => 2026, 'paidAmount' => 12001]);
    }

    public function testSettledPeriodIsBlocked(): void
    {
        $employee = $this->employee();
        [$service] = $this->service($employee, $this->firstPayment($employee), 112000);
        $context = $service->getPaymentContext(7, 10, 2026);
        self::assertFalse($context['canPay']);
        self::assertSame(0.0, $context['breakdown']['remaining']);
    }

    public function testFirstCalculationUsesCurrentRevenue(): void
    {
        $employee = $this->employee();
        [$service, , $reports] = $this->service($employee, null, 0);
        $reports->expects(self::once())->method('periodicDoctorReports')->willReturn(['doctors' => [['id' => 7, 'revenue_total' => 200000]]]);
        $context = $service->getPaymentContext(7, 10, 2026);
        self::assertFalse($context['salaryLocked']);
        self::assertSame(100000.0, $context['baseSalaryAmount']);
        self::assertSame(20000.0, $context['primeAmount']);
        self::assertSame(120000.0, $context['calculatedAmount']);
    }

    public function testEmployeeViewIncludesUnpaidEmployeesAndPaymentHistory(): void
    {
        $employee = $this->employee()->setTypeSalaire('fixe')->setValeurSalaire(100000)->setTypePrime('aucune');
        [$service, $payments] = $this->service($employee, null, 0);
        $payments->method('findBy')->willReturn([]);
        $rows = $service->listEmployeePayrolls(10, 2026);
        self::assertCount(1, $rows);
        self::assertSame(7, $rows[0]['id']);
        self::assertSame([], $rows[0]['payments']);
        self::assertSame(100000.0, $rows[0]['context']['calculatedAmount']);
    }

    public function testEditingAPaymentCannotChangeTheMonthlyCalculation(): void
    {
        $employee = $this->employee();
        $first = $this->firstPayment($employee);
        [$service, $payments] = $this->service($employee, $first, 30000);
        $payments->method('find')->willReturn($first);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('calcul mensuel est fige');
        $service->updateSalaryPayment(1, ['primeAmount' => 20000]);
    }
}
