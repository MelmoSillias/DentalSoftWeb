<?php

namespace App\IdentityAccess\Service;

use App\Billing\Entity\Transaction;
use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\SalaryPaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class PayrollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepository $employeRepo,
        private SalaryPaymentRepository $salaryPaymentRepo,
        private ModeDePaiementRepository $modeDePaiementRepo,
    ) {
    }

    public function listPayrolls(int $start, int $length, ?int $employeeId, ?int $month, ?int $year): array
    {
        $rows = $this->salaryPaymentRepo->findByFiltersPaginated($start, $length, $employeeId, $month, $year);

        $data = array_map(fn (SalaryPayment $payment) => $this->mapPaymentRow($payment), $rows);

        return [
            'data' => $data,
            'total' => $this->salaryPaymentRepo->count([]),
            'filtered' => $this->salaryPaymentRepo->countByFilters($employeeId, $month, $year),
        ];
    }

    public function getPaymentContext(int $employeeId, int $month, int $year): array
    {
        $employee = $this->employeRepo->find($employeeId);
        if (!$employee) {
            throw new InvalidArgumentException('Employe introuvable.');
        }

        $computed = $this->computeSalaryForPeriod($employee, $month, $year);
        $lastPayment = $this->salaryPaymentRepo->findLastPaymentForEmployee($employee);

        return [
            'employee' => [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fullname' => $employee->getFullName(),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'typeSalaire' => $employee->getTypeSalaire(),
                'valeurSalaire' => $employee->getValeurSalaire(),
                'matricule' => $employee->getMatricule(),
                'dateDernierPaiement' => $lastPayment?->getPaidAt()?->format('Y-m-d'),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
            ...$computed,
        ];
    }

    public function createSalaryPayment(array $payload): array
    {
        $employeeId = isset($payload['employeeId']) ? (int) $payload['employeeId'] : 0;
        $month = isset($payload['month']) ? (int) $payload['month'] : 0;
        $year = isset($payload['year']) ? (int) $payload['year'] : 0;

        if (!$employeeId || !$month || !$year) {
            throw new InvalidArgumentException('Employe, mois et annee sont requis.');
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Mois invalide.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Annee invalide.');
        }

        $employee = $this->employeRepo->find($employeeId);
        if (!$employee) {
            throw new InvalidArgumentException('Employe introuvable.');
        }

        $computed = $this->computeSalaryForPeriod($employee, $month, $year);

        $paidAmount = $this->normalizeAmount($payload['paidAmount'] ?? null, 'Montant verse');
        if ($paidAmount <= 0) {
            throw new InvalidArgumentException('Le montant verse doit etre superieur a 0.');
        }

        $paidAt = $this->parseDate($payload['paidAt'] ?? null);
        $note = trim((string) ($payload['note'] ?? ''));

        [$periodStart, $periodEnd] = $this->buildPeriodBounds($month, $year);

        $payment = (new SalaryPayment())
            ->setEmploye($employee)
            ->setMonth($month)
            ->setYear($year)
            ->setPeriodStart($periodStart)
            ->setPeriodEnd($periodEnd)
            ->setSalaryTypeSnapshot((string) ($computed['salaryType'] ?? 'non_defini'))
            ->setSalaryValueSnapshot($computed['salaryValue'] ?? null)
            ->setBaseAmount($computed['baseAmount'] ?? null)
            ->setCalculatedAmount((float) ($computed['calculatedAmount'] ?? 0))
            ->setPaidAmount($paidAmount)
            ->setPaidAt($paidAt)
            ->setNote($note !== '' ? $note : null);

        $this->registerSalaryExpenseTransaction($employee, $payment, $paidAmount);

        $this->em->persist($payment);
        $this->em->flush();

        return [
            'message' => 'Paiement de salaire enregistre.',
            'payment' => $this->mapPaymentRow($payment),
        ];
    }

    public function deleteSalaryPayment(int $id): array
    {
        $payment = $this->salaryPaymentRepo->find($id);
        if (!$payment) {
            throw new InvalidArgumentException('Paiement introuvable.');
        }

        $this->em->remove($payment);
        $this->em->flush();

        return ['message' => 'Paiement supprime.'];
    }

    public function getPrintPayload(int $id): array
    {
        $payment = $this->salaryPaymentRepo->find($id);
        if (!$payment) {
            throw new InvalidArgumentException('Paiement introuvable.');
        }

        $employee = $payment->getEmploye();

        return [
            'id' => $payment->getId(),
            'employee' => [
                'nom' => $employee?->getNom(),
                'prenom' => $employee?->getPrenom(),
                'fullname' => $employee?->getFullName(),
                'fonction' => $employee?->getFonction(),
                'matricule' => $employee?->getMatricule(),
            ],
            'period' => [
                'month' => $payment->getMonth(),
                'year' => $payment->getYear(),
                'start' => $payment->getPeriodStart()?->format('Y-m-d'),
                'end' => $payment->getPeriodEnd()?->format('Y-m-d'),
            ],
            'salaryType' => $payment->getSalaryTypeSnapshot(),
            'salaryValue' => $payment->getSalaryValueSnapshot(),
            'baseAmount' => $payment->getBaseAmount(),
            'calculatedAmount' => $payment->getCalculatedAmount(),
            'paidAmount' => $payment->getPaidAmount(),
            'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
            'note' => $payment->getNote(),
            'createdAt' => $payment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function mapPaymentRow(SalaryPayment $payment): array
    {
        $employee = $payment->getEmploye();

        return [
            'id' => $payment->getId(),
            'employeeId' => $employee?->getId(),
            'employeeName' => trim(($employee?->getPrenom() ?? '') . ' ' . ($employee?->getNom() ?? '')),
            'employeeFonction' => $employee?->getFonction(),
            'year' => $payment->getYear(),
            'month' => $payment->getMonth(),
            'periodStart' => $payment->getPeriodStart()?->format('Y-m-d'),
            'periodEnd' => $payment->getPeriodEnd()?->format('Y-m-d'),
            'salaryType' => $payment->getSalaryTypeSnapshot(),
            'salaryValue' => $payment->getSalaryValueSnapshot(),
            'baseAmount' => $payment->getBaseAmount(),
            'calculatedAmount' => $payment->getCalculatedAmount(),
            'paidAmount' => $payment->getPaidAmount(),
            'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
            'note' => $payment->getNote(),
        ];
    }

    private function computeSalaryForPeriod(Employe $employee, int $month, int $year): array
    {
        [$periodStart, $periodEnd] = $this->buildPeriodBounds($month, $year);

        $salaryType = (string) ($employee->getTypeSalaire() ?? 'non_defini');
        $salaryValue = $employee->getValeurSalaire();

        $baseAmount = null;
        $calculatedAmount = 0.0;

        if ($salaryType === 'fixe') {
            $baseAmount = $salaryValue;
            $calculatedAmount = (float) ($salaryValue ?? 0);
        } elseif ($salaryType === 'pourcentage' && $employee->getType() === 'Medecin') {
            $baseAmount = $this->computeMedecinBilledAmountForMonth($employee, $periodStart, $periodEnd);
            $calculatedAmount = $baseAmount * ((float) ($salaryValue ?? 0) / 100);
        }

        return [
            'periodStart' => $periodStart->format('Y-m-d'),
            'periodEnd' => $periodEnd->format('Y-m-d'),
            'salaryType' => $salaryType,
            'salaryValue' => $salaryValue,
            'baseAmount' => $baseAmount,
            'calculatedAmount' => round($calculatedAmount, 2),
        ];
    }

    private function computeMedecinBilledAmountForMonth(Employe $employee, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): float
    {
        $result = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(d.montant), 0)')
            ->from('App\\Billing\\Entity\\Devis', 'd')
            ->innerJoin('d.consultation', 'c')
            ->andWhere('c.medecin = :employee')
            ->andWhere('d.type = :factureType')
            ->andWhere('d.date BETWEEN :periodStart AND :periodEnd')
            ->setParameter('employee', $employee)
            ->setParameter('factureType', 1)
            ->setParameter('periodStart', $periodStart->setTime(0, 0, 0))
            ->setParameter('periodEnd', $periodEnd->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) $result, 2);
    }

    private function buildPeriodBounds(int $month, int $year): array
    {
        $periodStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $periodEnd = $periodStart->modify('last day of this month');

        return [$periodStart, $periodEnd];
    }

    private function normalizeAmount(mixed $value, string $field): float
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(sprintf('Le champ "%s" est requis.', $field));
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit etre numerique.', $field));
        }

        return round((float) $value, 2);
    }

    private function parseDate(mixed $value): \DateTimeInterface
    {
        if (!is_string($value) || trim($value) === '') {
            return new \DateTimeImmutable('today');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (!$date) {
            throw new InvalidArgumentException('Date de paiement invalide.');
        }

        return $date;
    }

    private function registerSalaryExpenseTransaction(Employe $employee, SalaryPayment $payment, float $paidAmount): void
    {
        $mode = $this->modeDePaiementRepo->findOneBy(['actif' => true, 'typeKey' => 'cash']);
        if (!$mode) {
            $mode = $this->modeDePaiementRepo->findOneBy(['actif' => true], ['id' => 'ASC']);
        }

        if (!$mode) {
            return;
        }

        $description = sprintf(
            'Paiement salaire %s %s (%02d/%04d)',
            (string) ($employee->getPrenom() ?? ''),
            (string) ($employee->getNom() ?? ''),
            $payment->getMonth(),
            $payment->getYear(),
        );

        $transaction = (new Transaction())
            ->setMontant((string) round($paidAmount))
            ->setType('Sortie')
            ->setMotif('Paiement salaire')
            ->setDescription(trim($description))
            ->setDateTransaction($payment->getPaidAt() ?? new \DateTimeImmutable())
            ->setModeDePaiement($mode)
            ->setValidationStatus('validated')
            ->setValidated(true)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setRolePaiement('direct');

        $this->em->persist($transaction);
    }
}
