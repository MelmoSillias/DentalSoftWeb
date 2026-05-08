<?php

namespace App\IdentityAccess\Service;
 
use App\CareDelivery\Service\ConsultationService;
use App\Focus\Service\FocusRealtimePublisher;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User as EntityUser;
use App\IdentityAccess\Repository\EmployeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class EmployeeService
{
    public function __construct(
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em,
        private ParameterBagInterface $params,
        private ConsultationService $consultationService,
        private FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    public function getEmployeesPageContext(): array
    {
        return [];
    }

    public function listEmployeesPaginated(int $start, int $length, string $searchValue): array
    {
        $employees = $this->employeRepo->findEmployeesWithPagination($start, $length, $searchValue);
        $totalRecords = $this->employeRepo->count([]);
        $filteredRecords = $this->employeRepo->countFiltered($searchValue);

        $data = array_map(function (Employe $employee) {
            return [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fullname' => $employee->getPrenom() . ' ' . $employee->getNom(),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'telephone' => $employee->getTelephone(),
                'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                'email' => $employee->getEmail(),
                'matricule' => $employee->getMatricule(),
                'typeContrat' => $employee->getTypeContrat(),
                'dureeContrat' => $employee->getDureeContrat(),
                'administrativeFiles' => $employee->getAdministrativeFiles(),
            ];
        }, $employees);

        return [
            'data' => $data,
            'total' => $totalRecords,
            'filtered' => $filteredRecords,
        ];
    }

    public function getEmployeeByUser(EntityUser $user): ?Employe
    {
        $employe =  $this->employeRepo->findOneBy(['user' => $user]);
        if (!$employe) return null; 

        return $employe;
    }

    public function createEmployee(array $data, array $files = []): array
    {
        $this->assertRequiredFields($data, [
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'fonction' => 'Fonction',
            'type' => 'Type',
            'dateEmbauche' => "Date d'embauche",
            'typeContrat' => 'Type de contrat',
        ]);

        $email = $this->normalizeNullableString($data['email'] ?? null);
        if ($email && $this->employeRepo->emailExists($email)) {
            throw new InvalidArgumentException("Cette adresse email est déjà associée à un employé.");
        }

        $dateEmbauche = $this->createDateFromInput($data['dateEmbauche'], "Date d'embauche");
        $typeSalaire = $this->normalizeTypeSalaire($data['typeSalaire'] ?? null);
        $valeurSalaire = $this->normalizeSalaireValue($typeSalaire, $data['type'] ?? null, $data['valeurSalaire'] ?? null);

        $typeContrat = $this->sanitizeString($data['typeContrat']);
        $dureeContrat = $this->normalizeDureeContrat($typeContrat, $data['dureeContrat'] ?? null);
        $comingDays = $this->normalizeComingDays($data['comingDays'] ?? []);

        $employe = new Employe();
        $employe->setNom($this->sanitizeString($data['nom']));
        $employe->setPrenom($this->sanitizeString($data['prenom']));
        $employe->setTelephone($this->normalizeNullableString($data['telephone'] ?? null));
        $employe->setFonction($this->sanitizeString($data['fonction']));
        $employe->setEmail($email);
        $employe->setType($this->sanitizeString($data['type']));
        $employe->setDateEmbauche($dateEmbauche);
        $employe->setTypeContrat($typeContrat);
        $employe->setDureeContrat($dureeContrat);
        $employe->setTypeSalaire($typeSalaire);
        $employe->setValeurSalaire($valeurSalaire);
        $employe->setComingDaysInWeek($comingDays);
        $employe->setIsOnDaysOff(false);
        $matricule = 'EMP-' . date('YmdHis');
        $employe->setMatricule($matricule);

        $savedFilePaths = $this->handleUpload($matricule, $files);
        $employe->setAdministrativeFiles($savedFilePaths);

        try {
            $this->em->persist($employe);
            $this->em->flush();
            $this->publishMedecinReferenceUpdate($employe, 'created');
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidArgumentException($this->buildUniqueConstraintMessage($exception));
        }

        return ['message' => 'Employé créé avec succès', 'id' => $employe->getId()];
    }

    public function updateEmployee(Employe $employee, array $data, array $files = []): array
    {
        $this->assertRequiredFields($data, [
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'fonction' => 'Fonction',
            'dateEmbauche' => "Date d'embauche",
            'typeContrat' => 'Type de contrat',
        ]);

        $email = $this->normalizeNullableString($data['email'] ?? null);
        if ($email && $this->employeRepo->emailExists($email, $employee->getId())) {
            throw new InvalidArgumentException("Cette adresse email est déjà associée à un autre employé.");
        }

        $matriculeInput = $data['matricule'] ?? null;
        if ($matriculeInput !== null && trim((string) $matriculeInput) !== '') {
            $matricule = $this->sanitizeString($matriculeInput);
            if ($this->employeRepo->matriculeExists($matricule, $employee->getId())) {
                throw new InvalidArgumentException('Ce matricule est déjà associé à un autre employé.');
            }
        } else {
            $matricule = $employee->getMatricule() ?? '';
        }

        $dateEmbauche = $this->createDateFromInput($data['dateEmbauche'], "Date d'embauche");
        $typeSalaire = $this->normalizeTypeSalaire($data['typeSalaire'] ?? $employee->getTypeSalaire());
        $valeurSalaire = $this->normalizeSalaireValue(
            $typeSalaire,
            $data['type'] ?? $employee->getType(),
            $data['valeurSalaire'] ?? $employee->getValeurSalaire()
        );

        $typeContrat = $this->sanitizeString($data['typeContrat']);
        $dureeContrat = $this->normalizeDureeContrat($typeContrat, $data['dureeContrat'] ?? $employee->getDureeContrat());
        $comingDays = $this->normalizeComingDays($data['comingDays'] ?? []);

        $employee->setNom($this->sanitizeString($data['nom']));
        $employee->setPrenom($this->sanitizeString($data['prenom']));
        $employee->setMatricule($matricule);
        $employee->setFonction($this->sanitizeString($data['fonction']));
        $employee->setTelephone($this->normalizeNullableString($data['telephone'] ?? null));
        $employee->setEmail($email);
        $employee->setDateEmbauche($dateEmbauche);
        $employee->setTypeSalaire($typeSalaire);
        $employee->setValeurSalaire($valeurSalaire);
        $employee->setTypeContrat($typeContrat);
        $employee->setDureeContrat($dureeContrat);
        $employee->setComingDaysInWeek($comingDays);

        $uploadDirMatricule = $employee->getMatricule();
        $savedFilePaths = $employee->getAdministrativeFiles();
        $newFiles = $this->handleUpload($uploadDirMatricule, $files);
        $employee->setAdministrativeFiles(array_merge($savedFilePaths, $newFiles));

        try {
            $this->em->flush();
            $this->publishMedecinReferenceUpdate($employee, 'updated');
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidArgumentException($this->buildUniqueConstraintMessage($exception));
        }

        return ['message' => 'Employé mis à jour avec succès'];
    }

    private function publishMedecinReferenceUpdate(Employe $employee, string $action): void
    {
        $type = strtolower((string) $employee->getType());
        $isMedecin = str_contains($type, 'medecin') || str_contains($type, 'médecin');
        if (!$isMedecin) {
            return;
        }

        $this->consultationService->invalidateStaffReferenceCache();
        $this->focusRealtimePublisher->publishMedecinRefresh($employee, $action);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeString(mixed $value): string
    {
        return trim((string) $value);
    }

    private function assertRequiredFields(array $data, array $fieldLabels): void
    {
        foreach ($fieldLabels as $fieldName => $label) {
            $value = $data[$fieldName] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                throw new InvalidArgumentException(sprintf('Le champ "%s" est requis.', $label));
            }
        }
    }

    private function normalizeOptionalInteger(mixed $value, string $fieldLabel): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit être un nombre.', $fieldLabel));
        }

        $intValue = (int) $value;

        if ($intValue < 0) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" ne peut pas être négatif.', $fieldLabel));
        }

        return $intValue;
    }

    private function normalizeTypeSalaire(?string $value): string
    {
        $normalized = $this->sanitizeString($value ?? '');

        if ($normalized === '') {
            return 'non_defini';
        }

        $allowed = ['fixe', 'pourcentage', 'non_defini'];
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException('Type de salaire invalide.');
        }

        return $normalized;
    }

    private function normalizeSalaireValue(string $typeSalaire, ?string $typeEmploye, mixed $value): ?float
    {
        if ($typeSalaire === 'non_defini') {
            return null;
        }

        if ($typeSalaire === 'pourcentage') {
            if ($typeEmploye !== 'Medecin') {
                throw new InvalidArgumentException("Le salaire en pourcentage est reserve aux medecins.");
            }

            $normalized = $value === null || $value === '' ? 35.0 : $this->normalizeAmount($value, 'Valeur du salaire');

            if ($normalized > 100) {
                throw new InvalidArgumentException('Le pourcentage ne peut pas depasser 100.');
            }

            return $normalized;
        }

        return $value === null || $value === ''
            ? 100000.0
            : $this->normalizeAmount($value, 'Valeur du salaire');
    }

    private function normalizeDureeContrat(string $typeContrat, mixed $value): ?int
    {
        if ($typeContrat === 'CDI') {
            return null;
        }

        if ($value === null || $value === '') {
            return 3;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Le champ "Duree du contrat" doit etre un nombre.');
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new InvalidArgumentException('La duree du contrat doit etre superieure a 0.');
        }

        return $intValue;
    }

    public function computeMedecinRevenue(Employe $employee): float
    {
        if ($employee->getType() !== 'Medecin') {
            return 0.0;
        }

        $total = 0.0;
        $seen = [];

        foreach ($employee->getConsultationsAsMedecin() as $consultation) {
            $paiement = $consultation->getPaiementDevis();
            if ($paiement && $paiement->getId() && !isset($seen[$paiement->getId()])) {
                $seen[$paiement->getId()] = true;
                $total += $paiement->getMontant();
            }

            $facture = $consultation->getFacture();
            if (!$facture) {
                continue;
            }

            foreach ($facture->getPaiements() as $pay) {
                $payId = $pay->getId();
                if ($payId && isset($seen[$payId])) {
                    continue;
                }
                if ($payId) {
                    $seen[$payId] = true;
                }
                $total += $pay->getMontant();
            }
        }

        return $total;
    }

    public function computeSalaireFromRevenue(Employe $employee, float $revenue): ?float
    {
        $typeSalaire = $employee->getTypeSalaire();
        $valeurSalaire = $employee->getValeurSalaire();

        if ($typeSalaire === 'non_defini') {
            return null;
        }

        if ($typeSalaire === 'pourcentage' && $employee->getType() === 'Medecin') {
            $pourcentage = $valeurSalaire ?? 0.0;
            return ($pourcentage / 100) * $revenue;
        }

        return $valeurSalaire;
    }

    private function normalizeAmount(mixed $value, string $fieldLabel): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit être un nombre.', $fieldLabel));
        }

        $amount = (float) $value;

        if ($amount < 0) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" ne peut pas être négatif.', $fieldLabel));
        }

        return $amount;
    }

    private function createDateFromInput(?string $value, string $fieldLabel): \DateTimeInterface
    {
        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Le champ "%s" est requis.', $fieldLabel));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" contient une date invalide.', $fieldLabel));
        }
    }

    private function normalizeComingDays(mixed $comingDays): array
    {
        if ($comingDays === null) {
            return [];
        }

        if (is_string($comingDays)) {
            $comingDays = [$comingDays];
        }

        if (!is_array($comingDays)) {
            throw new InvalidArgumentException('Le format des jours travaillés est invalide.');
        }

        $normalized = [];
        foreach ($comingDays as $day) {
            $trimmed = trim((string) $day);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function buildUniqueConstraintMessage(UniqueConstraintViolationException $exception): string
    {
        $message = $exception->getMessage();
        $constraints = [
            'UNIQ_F804D3B9E7927C74' => "Cette adresse email est déjà utilisée.",
        ];

        foreach ($constraints as $code => $friendlyMessage) {
            if (str_contains($message, $code)) {
                return $friendlyMessage;
            }
        }

        return "Une contrainte d'unicité a été violée. Veuillez vérifier les données saisies.";
    }

    private function handleUpload(string $matricule, array $files): array
    {
        if (!$files) {
            return [];
        }

        $uploadDir = $this->params->get('kernel.project_dir') . '/public/uploads/employes/' . $matricule;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $savedFilePaths = [];
        foreach ($files as $file) {
            $newFilename = Uuid::v4()->toRfc4122() . '.' . $file->guessExtension();
            $file->move($uploadDir, $newFilename);
            $savedFilePaths[] = '/uploads/employes/' . $matricule . '/' . $newFilename;
        }

        return $savedFilePaths;
    }
}
