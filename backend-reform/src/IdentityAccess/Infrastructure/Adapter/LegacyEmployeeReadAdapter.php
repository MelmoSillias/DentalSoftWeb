<?php

namespace App\IdentityAccess\Infrastructure\Adapter;

use App\CareDelivery\Service\ConsultationService;
use App\IdentityAccess\Application\Port\EmployeeReadPort;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\SalaryPayment;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use App\IdentityAccess\Service\EmployeeService;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Conge;

final class LegacyEmployeeReadAdapter implements EmployeeReadPort
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeRepository $employeRepository,
        private readonly ConsultationService $consultationService,
    ) {
    }

    public function listEmployeesPaginated(int $start, int $length, string $searchValue): array
    {
        return $this->employeeService->listEmployeesPaginated($start, $length, $searchValue);
    }

    public function getEmployeeDetails(int $employeeId): ?array
    {
        $employee = $this->employeRepository->find($employeeId);
        if (!$employee instanceof Employe) {
            return null;
        }

        $revenue = $this->employeeService->computeMedecinRevenue($employee);
        $salaireCalcule = $this->employeeService->computeSalaireFromRevenue($employee, $revenue);

        $conges = array_map(
            static fn(Conge $conge) => [
                'id' => $conge->getId(),
                'type' => $conge->getType(),
                'startDate' => $conge->getStartDate()?->format('Y-m-d'),
                'endDate' => $conge->getEndDate()?->format('Y-m-d'),
            ],
            $employee->getConges()->toArray()
        );

        $salaryPayments = array_map(
            static fn(SalaryPayment $payment) => [
                'id' => $payment->getId(),
                'month' => $payment->getMonth(),
                'year' => $payment->getYear(),
                'workedDay' => $payment->getWorkedDay()?->format('Y-m-d'),
                'frequenceSnapshot' => $payment->getFrequenceSnapshot(),
                'salaryType' => $payment->getSalaryTypeSnapshot(),
                'salaryValue' => $payment->getSalaryValueSnapshot(),
                'primeType' => $payment->getPrimeTypeSnapshot(),
                'primeValue' => $payment->getPrimeValueSnapshot(),
                'baseSalaryAmount' => $payment->getBaseSalaryAmount(),
                'primeAmount' => $payment->getPrimeAmount(),
                'baseAmount' => $payment->getBaseAmount(),
                'calculatedAmount' => $payment->getCalculatedAmount(),
                'paidAmount' => $payment->getPaidAmount(),
                'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
                'note' => $payment->getNote(),
                'paymentMethod' => $payment->getModeDePaiement() ? [
                    'id' => $payment->getModeDePaiement()->getId(),
                    'libelle' => $payment->getModeDePaiement()->getLibelle(),
                ] : null,
            ],
            $employee->getSalaryPayments()->toArray()
        );

        usort(
            $salaryPayments,
            static fn(array $left, array $right) => strcmp((string) ($right['paidAt'] ?? ''), (string) ($left['paidAt'] ?? ''))
        );

        return [
            'id' => $employee->getId(),
            'nom' => $employee->getNom(),
            'prenom' => $employee->getPrenom(),
            'fullname' => $employee->getFullName(),
            'matricule' => $employee->getMatricule(),
            'fonction' => $employee->getFonction(),
            'type' => $employee->getType(),
            'telephone' => $employee->getTelephone(),
            'email' => $employee->getEmail(),
            'dateEmbauche' => $employee->getDateEmbauche()?->format('Y-m-d'),
            'typeContrat' => $employee->getTypeContrat(),
            'dureeContrat' => $employee->getDureeContrat(),
            'typeSalaire' => $employee->getTypeSalaire(),
            'valeurSalaire' => $employee->getValeurSalaire(),
            'frequencePaiement' => $employee->getFrequencePaiement(),
            'typePrime' => $employee->getTypePrime(),
            'valeurPrime' => $employee->getValeurPrime(),
            'revenuMedecin' => $revenue,
            'salaireCalcule' => $salaireCalcule,
            'comingDays' => $employee->getComingDaysInWeek(),
            'administrativeFiles' => $employee->getAdministrativeFiles(),
            'conges' => $conges,
            'salaryPayments' => $salaryPayments,
        ];
    }

    public function listMedecins(): array
    {
        return $this->consultationService->listMedecins();
    }

    public function listInfirmiers(): array
    {
        return $this->consultationService->listInfirmiers();
    }
}
