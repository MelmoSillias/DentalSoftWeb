<?php

namespace App\Tests\IdentityAccess\Service;

use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\SalaryPaymentRepository;
use App\IdentityAccess\Service\PayrollService;
use App\Reporting\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PayrollPrintTest extends TestCase
{
    /** @dataProvider paymentAmounts */
    public function testSlipUsesSavedAmountsAndOnlyEarlierPayments(float $net, float $paid, float $previous, float $remaining): void
    {
        $employee = (new Employe())->setNom('Test')->setPrenom('Employe');
        $payment = (new SalaryPayment())
            ->setEmploye($employee)
            ->setMonth(10)
            ->setYear(2026)
            ->setBaseSalaryAmount($net)
            ->setCalculatedAmount($net)
            ->setPaidAmount($paid)
            ->setPaidAt(new \DateTime('2026-10-20'));

        $repository = $this->createMock(SalaryPaymentRepository::class);
        $repository->expects(self::once())->method('find')->with(42)->willReturn($payment);
        $repository->expects(self::once())->method('sumPaidBeforePayment')->with($payment)->willReturn($previous);
        // A slip must not recalculate its salary from the employee's current configuration.
        $reports = $this->createMock(ReportService::class);
        $service = new PayrollService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmployeRepository::class),
            $repository,
            $this->createMock(ModeDePaiementRepository::class),
            $reports,
        );

        $payload = $service->getPrintPayload(42);

        self::assertSame($net, $payload['netAPayer']);
        self::assertSame($paid, $payload['paidAmount']);
        self::assertSame($previous, $payload['previousPaidAmount']);
        self::assertSame($remaining, $payload['remainingAmount']);
    }

    public static function paymentAmounts(): array
    {
        return [
            'first partial payment' => [100000.0, 30000.0, 0.0, 70000.0],
            'second partial payment' => [100000.0, 20000.0, 30000.0, 50000.0],
            'final payment' => [100000.0, 50000.0, 50000.0, 0.0],
            'updated saved salary' => [120000.0, 50000.0, 30000.0, 40000.0],
            'decimal amounts' => [1000.55, 300.20, 200.10, 500.25],
        ];
    }
}
