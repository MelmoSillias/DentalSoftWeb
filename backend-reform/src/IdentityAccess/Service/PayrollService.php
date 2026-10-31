<?php

namespace App\IdentityAccess\Service;

use App\Billing\Entity\Transaction;
use App\Billing\Repository\ModeDePaiementRepository;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\SalaryPaymentRepository;
use App\Reporting\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class PayrollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepository $employeRepo,
        private SalaryPaymentRepository $salaryPaymentRepo,
        private ModeDePaiementRepository $modeDePaiementRepo,
        private ReportService $reportService,
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

    public function getPaymentContext(int $employeeId, int $month, int $year, ?string $day = null): array
    {
        $this->validatePeriod($month, $year);
        $employee = $this->employeRepo->find($employeeId);
        if (!$employee || $employee->isDeleted()) {
            throw new InvalidArgumentException('Employe introuvable.');
        }

        $frequence = $employee->getFrequencePaiement();
        if ($frequence === 'journalier') {
            if ($day === null || trim($day) === '') {
                throw new InvalidArgumentException('Le jour travaille est requis pour un employe en paiement journalier.');
            }
        }

        $computed = $this->computeSalaryForPeriod($employee, $month, $year, $day);
        $lastPayment = $this->salaryPaymentRepo->findLastPaymentForEmployee($employee);

        return [
            'employee' => [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fullname' => $employee->getFullName(),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'typeSalaire' => $computed['salaryType'],
                'valeurSalaire' => $computed['salaryValue'],
                'frequencePaiement' => $computed['frequencePaiement'],
                'typePrime' => $computed['typePrime'],
                'valeurPrime' => $computed['valeurPrime'],
                'matricule' => $employee->getMatricule(),
                'dateDernierPaiement' => $lastPayment?->getPaidAt()?->format('Y-m-d'),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'day' => $day,
            ],
            ...$computed,
        ];
    }

    public function listEmployeePayrolls(int $month, int $year, ?int $employeeId = null): array
    {
        $this->validatePeriod($month, $year);
        $employees = $this->employeRepo->findAllEmployees();
        $payments = $this->salaryPaymentRepo->findBy(['month' => $month, 'year' => $year], ['paidAt' => 'DESC', 'id' => 'DESC']);
        $byEmployee = [];
        foreach ($payments as $payment) {
            $byEmployee[$payment->getEmploye()->getId()][] = $this->mapPaymentRow($payment);
        }
        $reference = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $day = $reference->format('Y-m') === date('Y-m') ? date('Y-m-d') : $reference->format('Y-m-d');
        $rows = [];
        foreach ($employees as $employee) {
            if ($employeeId && $employee->getId() !== $employeeId) continue;
            $context = $this->getPaymentContext($employee->getId(), $month, $year, $employee->getFrequencePaiement() === 'journalier' ? $day : null);
            $rows[] = [
                'id' => $employee->getId(),
                'employeeName' => $employee->getFullName(),
                'employeeFonction' => $employee->getFonction(),
                'context' => $context,
                'payments' => $byEmployee[$employee->getId()] ?? [],
            ];
        }

        return $rows;
    }

    public function createSalaryPayment(array $payload): array
    {
        $employeeId = isset($payload['employeeId']) ? (int) $payload['employeeId'] : 0;
        $month = isset($payload['month']) ? (int) $payload['month'] : 0;
        $year = isset($payload['year']) ? (int) $payload['year'] : 0;
        $day = isset($payload['day']) ? trim((string) $payload['day']) : null;

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
        if (!$employee || $employee->isDeleted()) {
            throw new InvalidArgumentException('Employe introuvable.');
        }

        if ($employee->getFrequencePaiement() === 'journalier' && ($day === null || $day === '')) {
            throw new InvalidArgumentException('Le jour travaille est requis pour un employe en paiement journalier.');
        }

        $computed = $this->computeSalaryForPeriod($employee, $month, $year, $day);

        $primeOverride = isset($payload['primeAmount']) && $payload['primeAmount'] !== '' && $payload['primeAmount'] !== null
            ? $this->normalizeAmount($payload['primeAmount'], 'Montant prime')
            : null;

        $salaryLocked = $computed['salaryLocked'] ?? false;
        if (!$salaryLocked && $primeOverride !== null && $computed['typePrime'] !== 'fixe') {
            throw new InvalidArgumentException('Le montant de prime ne peut etre modifie que pour une prime fixe.');
        }

        $baseSalaryAmount = (float) ($computed['baseSalaryAmount'] ?? 0);
        $primeAmount = $salaryLocked ? (float) $computed['primeAmount'] : ($primeOverride ?? (float) ($computed['primeAmount'] ?? 0));
        $indemniteSpeciale = $salaryLocked ? (float) $computed['indemniteSpeciale'] : $this->normalizeAmount($payload['indemniteSpeciale'] ?? 0, 'Indemnite speciale');
        $inps = $salaryLocked ? (float) $computed['inps'] : $this->normalizeAmount($payload['inps'] ?? 0, 'INPS');
        $grossAmount = round($baseSalaryAmount + $primeAmount + $indemniteSpeciale, 2);
        if ($inps > $grossAmount) throw new InvalidArgumentException('La retenue INPS ne peut depasser le salaire brut.');
        $calculatedAmount = $salaryLocked ? (float) $computed['calculatedAmount'] : round($grossAmount - $inps, 2);
        $alreadyPaid = (float) ($computed['breakdown']['alreadyPaid'] ?? 0);
        $remaining = round(max(0, $calculatedAmount - $alreadyPaid), 2);

        if ($remaining <= 0.01) {
            throw new InvalidArgumentException('Cette periode est deja entierement reglee.');
        }

        $paidAmount = $this->normalizeAmount($payload['paidAmount'] ?? null, 'Montant verse');
        if ($paidAmount <= 0) {
            throw new InvalidArgumentException('Le montant verse doit etre superieur a 0.');
        }

        if ($paidAmount > $remaining + 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Le montant verse (%.2f) depasse le reste a payer (%.2f).',
                $paidAmount,
                $remaining
            ));
        }

        $paidAt = $this->parseDate($payload['paidAt'] ?? null);
        $note = trim((string) ($payload['note'] ?? ''));
        $mode = $this->resolvePaymentMethod($payload['paymentMethodId'] ?? null);

        [$periodStart, $periodEnd] = $this->buildPeriodBounds($employee, $month, $year, $day);
        $workedDay = $employee->getFrequencePaiement() === 'journalier' ? $periodStart : null;

        $payment = (new SalaryPayment())
            ->setEmploye($employee)
            ->setMonth($month)
            ->setYear($year)
            ->setPeriodStart($periodStart)
            ->setPeriodEnd($periodEnd)
            ->setWorkedDay($workedDay)
            ->setFrequenceSnapshot($employee->getFrequencePaiement())
            ->setSalaryTypeSnapshot((string) ($computed['salaryType'] ?? 'non_defini'))
            ->setSalaryValueSnapshot($computed['salaryValue'] ?? null)
            ->setPrimeTypeSnapshot($computed['typePrime'])
            ->setPrimeValueSnapshot($computed['valeurPrime'])
            ->setBaseAmount($computed['baseAmount'] ?? null)
            ->setBaseSalaryAmount($baseSalaryAmount)
            ->setPrimeAmount($primeAmount)
            ->setIndemniteSpeciale($indemniteSpeciale)
            ->setInps($inps)
            ->setPrimeBaseAmount($computed['primeBaseAmount'] ?? null)
            ->setCalculatedAmount($calculatedAmount)
            ->setPaidAmount($paidAmount)
            ->setPaidAt($paidAt)
            ->setNote($note !== '' ? $note : null)
            ->setModeDePaiement($mode);

        $transaction = $this->registerSalaryExpenseTransaction($employee, $payment, $paidAmount, $mode);
        $payment->setTransaction($transaction);

        $this->em->persist($payment);
        $this->em->flush();

        return [
            'message' => 'Paiement de salaire enregistre.',
            'payment' => $this->mapPaymentRow($payment),
        ];
    }

    public function getSalaryPayment(int $id): array
    {
        $payment = $this->salaryPaymentRepo->find($id);
        if (!$payment) {
            throw new InvalidArgumentException('Paiement introuvable.');
        }

        return $this->mapPaymentDetail($payment);
    }

    public function updateSalaryPayment(int $id, array $payload): array
    {
        $payment = $this->salaryPaymentRepo->find($id);
        if (!$payment) {
            throw new InvalidArgumentException('Paiement introuvable.');
        }

        $employee = $payment->getEmploye();
        if (!$employee) {
            throw new InvalidArgumentException('Employe introuvable.');
        }

        $primeOverride = isset($payload['primeAmount']) && $payload['primeAmount'] !== '' && $payload['primeAmount'] !== null
            ? $this->normalizeAmount($payload['primeAmount'], 'Montant prime')
            : null;

        if ($primeOverride !== null && $payment->getPrimeTypeSnapshot() !== 'fixe') {
            throw new InvalidArgumentException('Le montant de prime ne peut etre modifie que pour une prime fixe.');
        }

        $baseSalaryAmount = $payment->getBaseSalaryAmount();
        $primeAmount = $primeOverride ?? $payment->getPrimeAmount();
        $indemniteSpeciale = array_key_exists('indemniteSpeciale', $payload) ? $this->normalizeAmount($payload['indemniteSpeciale'], 'Indemnite speciale') : $payment->getIndemniteSpeciale();
        $inps = array_key_exists('inps', $payload) ? $this->normalizeAmount($payload['inps'], 'INPS') : $payment->getInps();
        if ($payment->getFrequenceSnapshot() === 'mensuel' && (
            $primeAmount !== $payment->getPrimeAmount() || $indemniteSpeciale !== $payment->getIndemniteSpeciale() || $inps !== $payment->getInps()
        )) {
            throw new InvalidArgumentException('Le calcul mensuel est fige apres le premier paiement.');
        }
        $grossAmount = round($baseSalaryAmount + $primeAmount + $indemniteSpeciale, 2);
        if ($inps > $grossAmount) throw new InvalidArgumentException('La retenue INPS ne peut depasser le salaire brut.');
        $calculatedAmount = round($grossAmount - $inps, 2);

        $paidAmount = isset($payload['paidAmount']) && $payload['paidAmount'] !== ''
            ? $this->normalizeAmount($payload['paidAmount'], 'Montant verse')
            : $payment->getPaidAmount();

        if ($paidAmount <= 0) {
            throw new InvalidArgumentException('Le montant verse doit etre superieur a 0.');
        }

        $alreadyPaidOthers = $this->computeAlreadyPaidExcluding($employee, $payment);
        $maxAllowed = round(max(0, $calculatedAmount - $alreadyPaidOthers), 2);
        if ($paidAmount > $maxAllowed + 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Le montant verse (%.2f) depasse le reste autorise (%.2f).',
                $paidAmount,
                $maxAllowed
            ));
        }

        if (isset($payload['paidAt'])) {
            $payment->setPaidAt($this->parseDate($payload['paidAt']));
        }

        if (array_key_exists('note', $payload)) {
            $note = trim((string) ($payload['note'] ?? ''));
            $payment->setNote($note !== '' ? $note : null);
        }

        if (isset($payload['paymentMethodId'])) {
            $mode = $this->resolvePaymentMethod($payload['paymentMethodId']);
            $payment->setModeDePaiement($mode);
        }

        $payment
            ->setPrimeAmount($primeAmount)
            ->setIndemniteSpeciale($indemniteSpeciale)
            ->setInps($inps)
            ->setCalculatedAmount($calculatedAmount)
            ->setPaidAmount($paidAmount);

        $mode = $payment->getModeDePaiement();
        if ($mode) {
            $transaction = $payment->getTransaction();
            if ($transaction) {
                $transaction
                    ->setMontant((string) round($paidAmount))
                    ->setDateTransaction($payment->getPaidAt() ?? new \DateTimeImmutable())
                    ->setModeDePaiement($mode);
            }
        }

        $this->em->flush();

        return [
            'message' => 'Paiement mis a jour.',
            'payment' => $this->mapPaymentRow($payment),
        ];
    }

    public function listActivePaymentMethods(): array
    {
        $methods = $this->modeDePaiementRepo->findBy(['actif' => true], ['libelle' => 'ASC']);

        return array_map(static fn ($mode) => [
            'id' => $mode->getId(),
            'libelle' => $mode->getLibelle(),
            'type' => $mode->getType(),
        ], $methods);
    }

    public function deleteSalaryPayment(int $id): array
    {
        $payment = $this->salaryPaymentRepo->find($id);
        if (!$payment) {
            throw new InvalidArgumentException('Paiement introuvable.');
        }

        $transaction = $payment->getTransaction();
        if ($transaction) {
            $this->em->remove($transaction);
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
        $previousPaidAmount = $this->salaryPaymentRepo->sumPaidBeforePayment($payment);

        return [
            'id' => $payment->getId(),
            'employee' => [
                'nom' => $employee?->getNom(),
                'prenom' => $employee?->getPrenom(),
                'fullname' => $employee?->getFullName(),
                'fonction' => $employee?->getFonction(),
                'adresse' => $employee?->getAdresse(),
                'titre' => $employee?->getTitre(),
                'matricule' => $employee?->getMatricule(),
            ],
            'period' => [
                'month' => $payment->getMonth(),
                'year' => $payment->getYear(),
                'day' => $payment->getWorkedDay()?->format('Y-m-d'),
                'start' => $payment->getPeriodStart()?->format('Y-m-d'),
                'end' => $payment->getPeriodEnd()?->format('Y-m-d'),
            ],
            'frequenceSnapshot' => $payment->getFrequenceSnapshot(),
            'salaryType' => $payment->getSalaryTypeSnapshot(),
            'salaryValue' => $payment->getSalaryValueSnapshot(),
            'primeType' => $payment->getPrimeTypeSnapshot(),
            'primeValue' => $payment->getPrimeValueSnapshot(),
            'baseAmount' => $payment->getBaseAmount(),
            'baseSalaryAmount' => $payment->getBaseSalaryAmount(),
            'primeAmount' => $payment->getPrimeAmount(),
            'indemniteSpeciale' => $payment->getIndemniteSpeciale(),
            'inps' => $payment->getInps(),
            'salaireBrut' => round($payment->getBaseSalaryAmount() + $payment->getPrimeAmount() + $payment->getIndemniteSpeciale(), 2),
            'totalRetenues' => $payment->getInps(),
            'netAPayer' => $payment->getCalculatedAmount(),
            'previousPaidAmount' => $previousPaidAmount,
            'remainingAmount' => round(max(0, $payment->getCalculatedAmount() - $previousPaidAmount - $payment->getPaidAmount()), 2),
            'calculatedAmount' => $payment->getCalculatedAmount(),
            'paidAmount' => $payment->getPaidAmount(),
            'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
            'note' => $payment->getNote(),
            'paymentMethod' => $this->mapPaymentMethod($payment),
            'createdAt' => $payment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function mapPaymentDetail(SalaryPayment $payment): array
    {
        return [
            ...$this->mapPaymentRow($payment),
            'employee' => [
                'id' => $payment->getEmploye()?->getId(),
                'fullname' => $payment->getEmploye()?->getFullName(),
                'fonction' => $payment->getEmploye()?->getFonction(),
                'titre' => $payment->getEmploye()?->getTitre(),
                'adresse' => $payment->getEmploye()?->getAdresse(),
                'matricule' => $payment->getEmploye()?->getMatricule(),
            ],
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
            'workedDay' => $payment->getWorkedDay()?->format('Y-m-d'),
            'frequenceSnapshot' => $payment->getFrequenceSnapshot(),
            'periodStart' => $payment->getPeriodStart()?->format('Y-m-d'),
            'periodEnd' => $payment->getPeriodEnd()?->format('Y-m-d'),
            'salaryType' => $payment->getSalaryTypeSnapshot(),
            'salaryValue' => $payment->getSalaryValueSnapshot(),
            'primeType' => $payment->getPrimeTypeSnapshot(),
            'primeValue' => $payment->getPrimeValueSnapshot(),
            'primeBaseAmount' => $payment->getPrimeBaseAmount(),
            'baseAmount' => $payment->getBaseAmount(),
            'baseSalaryAmount' => $payment->getBaseSalaryAmount(),
            'primeAmount' => $payment->getPrimeAmount(),
            'indemniteSpeciale' => $payment->getIndemniteSpeciale(),
            'inps' => $payment->getInps(),
            'calculatedAmount' => $payment->getCalculatedAmount(),
            'paidAmount' => $payment->getPaidAmount(),
            'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
            'note' => $payment->getNote(),
            'paymentMethod' => $this->mapPaymentMethod($payment),
        ];
    }

    private function mapPaymentMethod(SalaryPayment $payment): ?array
    {
        $mode = $payment->getModeDePaiement();
        if (!$mode) {
            return null;
        }

        return [
            'id' => $mode->getId(),
            'libelle' => $mode->getLibelle(),
            'type' => $mode->getType(),
        ];
    }

    private function computeAlreadyPaidExcluding(Employe $employee, SalaryPayment $exclude): float
    {
        if ($exclude->getFrequenceSnapshot() === 'journalier' && $exclude->getWorkedDay()) {
            $total = $this->salaryPaymentRepo->sumPaidForWorkedDay($employee, $exclude->getWorkedDay());

            return round(max(0, $total - $exclude->getPaidAmount()), 2);
        }

        $total = $this->salaryPaymentRepo->sumPaidForMonthlyPeriod($employee, $exclude->getMonth(), $exclude->getYear());

        return round(max(0, $total - $exclude->getPaidAmount()), 2);
    }

    private function computeSalaryForPeriod(Employe $employee, int $month, int $year, ?string $day = null): array
    {
        [$periodStart, $periodEnd] = $this->buildPeriodBounds($employee, $month, $year, $day);

        $firstPayment = $this->salaryPaymentRepo->findFirstForPeriod($employee, $month, $year, $day);
        if ($firstPayment) {
            $alreadyPaid = $this->computeAlreadyPaid($employee, $month, $year, $day, $periodStart);
            $remaining = round(max(0, $firstPayment->getCalculatedAmount() - $alreadyPaid), 2);

            return [
                'salaryLocked' => true,
                'firstPaymentId' => $firstPayment->getId(),
                'periodStart' => $firstPayment->getPeriodStart()?->format('Y-m-d'),
                'periodEnd' => $firstPayment->getPeriodEnd()?->format('Y-m-d'),
                'salaryType' => $firstPayment->getSalaryTypeSnapshot(),
                'salaryValue' => $firstPayment->getSalaryValueSnapshot(),
                'frequencePaiement' => $firstPayment->getFrequenceSnapshot(),
                'typePrime' => $firstPayment->getPrimeTypeSnapshot(),
                'valeurPrime' => $firstPayment->getPrimeValueSnapshot(),
                'baseAmount' => $firstPayment->getBaseAmount(),
                'primeBaseAmount' => $firstPayment->getPrimeBaseAmount(),
                'baseSalaryAmount' => $firstPayment->getBaseSalaryAmount(),
                'primeAmount' => $firstPayment->getPrimeAmount(),
                'indemniteSpeciale' => $firstPayment->getIndemniteSpeciale(),
                'inps' => $firstPayment->getInps(),
                'calculatedAmount' => $firstPayment->getCalculatedAmount(),
                'breakdown' => [
                    'baseSalary' => $firstPayment->getBaseSalaryAmount(),
                    'prime' => $firstPayment->getPrimeAmount(),
                    'total' => $firstPayment->getCalculatedAmount(),
                    'alreadyPaid' => $alreadyPaid,
                    'remaining' => $remaining,
                ],
                'canPay' => $remaining > 0.01,
                'blockReason' => $remaining > 0.01 ? null : 'Cette periode est deja entierement reglee.',
            ];
        }

        $salaryType = (string) ($employee->getTypeSalaire() ?? 'non_defini');
        $salaryValue = $employee->getValeurSalaire();
        $frequence = $employee->getFrequencePaiement();
        $typePrime = $employee->getTypePrime();
        $valeurPrime = $employee->getValeurPrime();
        $isDoctor = $employee->getType() === 'Medecin';
        $usesCollectedRevenue = $isDoctor && (
            $salaryType === 'pourcentage' || $typePrime === 'actes'
        );
        $collectedRevenue = $usesCollectedRevenue
            ? $this->computeMedecinCollectedAmountForPeriod($employee, $periodStart, $periodEnd)
            : null;

        $baseAmount = null;
        $baseSalaryAmount = 0.0;

        if ($salaryType === 'fixe') {
            $baseAmount = $salaryValue;
            $baseSalaryAmount = (float) ($salaryValue ?? 0);
        } elseif ($salaryType === 'pourcentage' && $isDoctor) {
            $baseAmount = $collectedRevenue;
            $baseSalaryAmount = $baseAmount * ((float) ($salaryValue ?? 0) / 100);
        }

        $primeBaseAmount = null;
        $primeAmount = 0.0;

        if ($typePrime === 'fixe') {
            $primeAmount = (float) ($valeurPrime ?? 0);
        } elseif ($typePrime === 'actes' && $isDoctor) {
            $primeBaseAmount = $collectedRevenue;
            $primeAmount = $primeBaseAmount * ((float) ($valeurPrime ?? 0) / 100);
        }

        $baseSalaryAmount = round($baseSalaryAmount, 2);
        $primeAmount = round($primeAmount, 2);
        $total = round($baseSalaryAmount + $primeAmount, 2);

        $alreadyPaid = $this->computeAlreadyPaid($employee, $month, $year, $day, $periodStart);
        $remaining = round(max(0, $total - $alreadyPaid), 2);
        $canPay = $remaining > 0.01;

        return [
            'periodStart' => $periodStart->format('Y-m-d'),
            'salaryLocked' => false,
            'indemniteSpeciale' => 0.0,
            'inps' => 0.0,
            'periodEnd' => $periodEnd->format('Y-m-d'),
            'salaryType' => $salaryType,
            'salaryValue' => $salaryValue,
            'frequencePaiement' => $frequence,
            'typePrime' => $typePrime,
            'valeurPrime' => $valeurPrime,
            'baseAmount' => $baseAmount,
            'primeBaseAmount' => $primeBaseAmount,
            'baseSalaryAmount' => $baseSalaryAmount,
            'primeAmount' => $primeAmount,
            'calculatedAmount' => $total,
            'breakdown' => [
                'baseSalary' => $baseSalaryAmount,
                'prime' => $primeAmount,
                'total' => $total,
                'alreadyPaid' => $alreadyPaid,
                'remaining' => $remaining,
            ],
            'canPay' => $canPay,
            'blockReason' => $canPay ? null : 'Cette periode est deja entierement reglee.',
        ];
    }

    private function computeAlreadyPaid(Employe $employee, int $month, int $year, ?string $day, \DateTimeImmutable $periodStart): float
    {
        if ($employee->getFrequencePaiement() === 'journalier') {
            return $this->salaryPaymentRepo->sumPaidForWorkedDay($employee, $periodStart);
        }

        return $this->salaryPaymentRepo->sumPaidForMonthlyPeriod($employee, $month, $year);
    }

    private function computeMedecinCollectedAmountForPeriod(Employe $employee, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): float
    {
        $report = $this->reportService->periodicDoctorReports(
            $periodStart->setTime(0, 0, 0),
            $periodEnd->setTime(23, 59, 59),
        );

        foreach ($report['doctors'] as $doctorStats) {
            if ((int) ($doctorStats['id'] ?? 0) === (int) $employee->getId()) {
                return round((float) ($doctorStats['revenue_total'] ?? 0), 2);
            }
        }

        return 0.0;
    }

    private function buildPeriodBounds(Employe $employee, int $month, int $year, ?string $day): array
    {
        if ($employee->getFrequencePaiement() === 'journalier') {
            $workedDay = $this->parseDay($day, $month, $year);
            return [$workedDay, $workedDay];
        }

        $periodStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $periodEnd = $periodStart->modify('last day of this month');

        return [$periodStart, $periodEnd];
    }

    private function parseDay(?string $day, int $month, int $year): \DateTimeImmutable
    {
        if ($day === null || trim($day) === '') {
            throw new InvalidArgumentException('Le jour travaille est requis.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($day));
        if (!$date) {
            throw new InvalidArgumentException('Jour travaille invalide.');
        }

        if ((int) $date->format('n') !== $month || (int) $date->format('Y') !== $year) {
            throw new InvalidArgumentException('Le jour travaille doit correspondre au mois et a l\'annee selectionnes.');
        }

        return $date;
    }

    private function validatePeriod(int $month, int $year): void
    {
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Mois ou annee invalide.');
        }
    }

    private function normalizeAmount(mixed $value, string $field): float
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException(sprintf('Le champ "%s" est requis.', $field));
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit etre numerique.', $field));
        }

        if ((float) $value < 0) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" ne peut etre negatif.', $field));
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

    private function registerSalaryExpenseTransaction(
        Employe $employee,
        SalaryPayment $payment,
        float $paidAmount,
        \App\Billing\Entity\ModeDePaiement $mode,
    ): Transaction {
        $description = sprintf(
            'Paiement salaire %s %s (%02d/%04d)',
            (string) ($employee->getPrenom() ?? ''),
            (string) ($employee->getNom() ?? ''),
            $payment->getMonth(),
            $payment->getYear(),
        );

        $transaction = (new Transaction())
            ->setMontant((string) round($paidAmount))
            ->setType('Depense')
            ->setMotif('Paiement salaire')
            ->setDescription(trim($description))
            ->setDateTransaction($payment->getPaidAt() ?? new \DateTimeImmutable())
            ->setModeDePaiement($mode)
            ->setValidationStatus('validated')
            ->setValidated(true)
            ->setValidatedAt(new \DateTimeImmutable());

        $this->em->persist($transaction);

        return $transaction;
    }

    private function resolvePaymentMethod(mixed $paymentMethodId): \App\Billing\Entity\ModeDePaiement
    {
        $id = isset($paymentMethodId) ? (int) $paymentMethodId : 0;
        if ($id <= 0) {
            throw new InvalidArgumentException('Le mode de paiement est requis.');
        }

        $mode = $this->modeDePaiementRepo->find($id);
        if (!$mode || !$mode->isActif()) {
            throw new InvalidArgumentException('Mode de paiement invalide ou inactif.');
        }

        return $mode;
    }
}
