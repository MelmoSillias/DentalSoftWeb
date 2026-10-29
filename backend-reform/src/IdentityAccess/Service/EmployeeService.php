<?php

namespace App\IdentityAccess\Service;
 
use App\CareDelivery\Service\ConsultationService;
use App\Focus\Service\FocusRealtimePublisher;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User as EntityUser;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\StaffRoleCatalog;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
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
        $totalRecords = $this->employeRepo->count(['deletedAt' => null]);
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
                'typeSalaire' => $employee->getTypeSalaire(),
                'frequencePaiement' => $employee->getFrequencePaiement(),
                'typePrime' => $employee->getTypePrime(),
                'administrativeFiles' => $employee->getAdministrativeFiles(),
            ];
        }, $employees);

        return [
            'data' => $data,
            'total' => $totalRecords,
            'filtered' => $filteredRecords,
        ];
    }

    public function listDeletedEmployeesPaginated(int $page, int $limit, ?string $query = null): array
    {
        $resolvedPage = max(1, $page);
        $resolvedLimit = max(1, min($limit, 100));
        $result = $this->employeRepo->paginateDeletedEmployees($resolvedPage, $resolvedLimit, $query);

        $items = array_map(static function (Employe $employee): array {
            return [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fullname' => trim(($employee->getPrenom() ?? '') . ' ' . ($employee->getNom() ?? '')),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'telephone' => $employee->getTelephone(),
                'deletedAt' => $employee->getDeletedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $result['items']);

        return [
            'items' => $items,
            'total' => $result['total'],
            'page' => $resolvedPage,
            'limit' => $resolvedLimit,
        ];
    }

    public function softDeleteEmployee(int $id): array
    {
        $employee = $this->employeRepo->find($id);
        if (!$employee instanceof Employe || $employee->isDeleted()) {
            return ['error' => 'Employé introuvable', 'status' => 404];
        }

        $employee->setDeletedAt(new DateTimeImmutable());
        $user = $employee->getUser();
        if ($user !== null) {
            $user->setActive(false);
        }

        $this->em->flush();
        $this->publishMedecinReferenceUpdate($employee, 'deleted');

        return [
            'success' => true,
            'message' => 'Employé déplacé dans la corbeille.',
        ];
    }

    public function restoreEmployee(int $id): array
    {
        $employee = $this->employeRepo->find($id);
        if (!$employee instanceof Employe || !$employee->isDeleted()) {
            return ['error' => 'Employé introuvable dans la corbeille', 'status' => 404];
        }

        $employee->setDeletedAt(null);
        $user = $employee->getUser();
        if ($user !== null) {
            $user->setActive(true);
        }

        $this->em->flush();
        $this->publishMedecinReferenceUpdate($employee, 'restored');

        return [
            'success' => true,
            'message' => 'Employé restauré avec succès.',
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
            'type' => 'Type',
            'dateEmbauche' => "Date d'embauche",
            'typeContrat' => 'Type de contrat',
        ]);

        $employeeType = StaffRoleCatalog::assertAllowedEmployeeType($data['type'] ?? null);
        $fonction = $this->normalizeNullableString($data['fonction'] ?? null) ?? '';

        $email = $this->normalizeNullableString($data['email'] ?? null);
        if ($email && $this->employeRepo->emailExists($email)) {
            throw new InvalidArgumentException("Cette adresse email est déjÃ  associée Ã  un employé.");
        }

        $dateEmbauche = $this->createDateFromInput($data['dateEmbauche'], "Date d'embauche");
        $typeSalaire = $this->normalizeTypeSalaire($data['typeSalaire'] ?? null);
        $valeurSalaire = $this->normalizeSalaireValue($typeSalaire, $data['type'] ?? null, $data['valeurSalaire'] ?? null);
        $frequencePaiement = $this->normalizeFrequencePaiement($data['frequencePaiement'] ?? null);
        $typePrime = $this->normalizeTypePrime($data['typePrime'] ?? null, $data['type'] ?? null);
        $valeurPrime = $this->normalizePrimeValue($typePrime, $data['valeurPrime'] ?? null);

        $typeContrat = $this->sanitizeString($data['typeContrat']);
        $dureeContrat = $this->normalizeDureeContrat($typeContrat, $data['dureeContrat'] ?? null);
        $comingDays = $this->normalizeComingDays($data['comingDays'] ?? []);

        $employe = new Employe();
        $employe->setNom($this->sanitizeString($data['nom']));
        $employe->setPrenom($this->sanitizeString($data['prenom']));
        $employe->setTelephone($this->normalizeNullableString($data['telephone'] ?? null));
        $employe->setFonction($fonction);
        $employe->setEmail($email);
        $employe->setType($employeeType);
        $employe->setDateEmbauche($dateEmbauche);
        $employe->setTypeContrat($typeContrat);
        $employe->setDureeContrat($dureeContrat);
        $employe->setTypeSalaire($typeSalaire);
        $employe->setValeurSalaire($valeurSalaire);
        $employe->setFrequencePaiement($frequencePaiement);
        $employe->setTypePrime($typePrime);
        $employe->setValeurPrime($valeurPrime);
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

        return ['message' => 'Employé créé avec succÃ¨s', 'id' => $employe->getId()];
    }

    public function updateEmployee(Employe $employee, array $data, array $files = []): array
    {
        if ($employee->isDeleted()) {
            throw new InvalidArgumentException('Cet employé est dans la corbeille.');
        }

        $this->assertRequiredFields($data, [
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'type' => 'Type',
            'dateEmbauche' => "Date d'embauche",
            'typeContrat' => 'Type de contrat',
        ]);

        $employeeType = StaffRoleCatalog::assertAllowedEmployeeType($data['type'] ?? null);
        $fonction = array_key_exists('fonction', $data)
            ? ($this->normalizeNullableString($data['fonction']) ?? '')
            : ($employee->getFonction() ?? '');

        $email = $this->normalizeNullableString($data['email'] ?? null);
        if ($email && $this->employeRepo->emailExists($email, $employee->getId())) {
            throw new InvalidArgumentException("Cette adresse email est déjÃ  associée Ã  un autre employé.");
        }

        $matriculeInput = $data['matricule'] ?? null;
        if ($matriculeInput !== null && trim((string) $matriculeInput) !== '') {
            $matricule = $this->sanitizeString($matriculeInput);
            if ($this->employeRepo->matriculeExists($matricule, $employee->getId())) {
                throw new InvalidArgumentException('Ce matricule est déjÃ  associé Ã  un autre employé.');
            }
        } else {
            $matricule = $employee->getMatricule() ?? '';
        }

        $dateEmbauche = $this->createDateFromInput($data['dateEmbauche'], "Date d'embauche");
        $typeSalaire = $this->normalizeTypeSalaire($data['typeSalaire'] ?? $employee->getTypeSalaire());
        $valeurSalaire = $this->normalizeSalaireValue(
            $typeSalaire,
            $data['type'] ?? $employee->getType(),
            array_key_exists('valeurSalaire', $data) ? $data['valeurSalaire'] : $employee->getValeurSalaire()
        );
        $frequencePaiement = $this->normalizeFrequencePaiement($data['frequencePaiement'] ?? $employee->getFrequencePaiement());
        $typePrime = $this->normalizeTypePrime(
            array_key_exists('typePrime', $data) ? $data['typePrime'] : $employee->getTypePrime(),
            $data['type'] ?? $employee->getType()
        );
        $valeurPrime = $this->normalizePrimeValue(
            $typePrime,
            array_key_exists('valeurPrime', $data) ? $data['valeurPrime'] : $employee->getValeurPrime()
        );

        $typeContrat = $this->sanitizeString($data['typeContrat']);
        $dureeContrat = $this->normalizeDureeContrat(
            $typeContrat,
            array_key_exists('dureeContrat', $data) ? $data['dureeContrat'] : $employee->getDureeContrat()
        );
        $comingDays = array_key_exists('comingDays', $data) || array_key_exists('comingDays[]', $data)
            ? $this->normalizeComingDays($data['comingDays'] ?? $data['comingDays[]'])
            : $employee->getComingDaysInWeek();

        $employee->setNom($this->sanitizeString($data['nom']));
        $employee->setPrenom($this->sanitizeString($data['prenom']));
        $employee->setMatricule($matricule);
        $employee->setFonction($fonction);
        $employee->setType($employeeType);
        $employee->setTelephone($this->normalizeNullableString($data['telephone'] ?? null));
        $employee->setEmail($email);
        $employee->setDateEmbauche($dateEmbauche);
        $employee->setTypeSalaire($typeSalaire);
        $employee->setValeurSalaire($valeurSalaire);
        $employee->setFrequencePaiement($frequencePaiement);
        $employee->setTypePrime($typePrime);
        $employee->setValeurPrime($valeurPrime);
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

        return ['message' => 'Employé mis Ã  jour avec succÃ¨s'];
    }

    private function publishMedecinReferenceUpdate(Employe $employee, string $action): void
    {
        $type = strtolower((string) $employee->getType());
        $isMedecin = str_contains($type, 'medecin') || str_contains($type, 'médecin');
        $isInfirmier = str_contains($type, 'infirmier');
        if (!$isMedecin && !$isInfirmier) {
            return;
        }

        $this->consultationService->invalidateStaffReferenceCache();

        if ($isMedecin) {
            $this->focusRealtimePublisher->publishMedecinRefresh($employee, $action);
        }
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
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit Ãªtre un nombre.', $fieldLabel));
        }

        $intValue = (int) $value;

        if ($intValue < 0) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" ne peut pas Ãªtre négatif.', $fieldLabel));
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

    private function normalizeFrequencePaiement(?string $value): string
    {
        $normalized = $this->sanitizeString($value ?? '');

        if ($normalized === '') {
            return 'mensuel';
        }

        $allowed = ['mensuel', 'journalier'];
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException('Frequence de paiement invalide.');
        }

        return $normalized;
    }

    private function normalizeTypePrime(?string $value, ?string $typeEmploye): string
    {
        $normalized = $this->sanitizeString($value ?? '');

        if ($normalized === '') {
            return 'aucune';
        }

        $allowed = ['aucune', 'fixe', 'actes'];
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException('Type de prime invalide.');
        }

        if ($normalized === 'actes' && $typeEmploye !== 'Medecin') {
            throw new InvalidArgumentException('La prime sur actes est reservee aux medecins.');
        }

        return $normalized;
    }

    private function normalizePrimeValue(string $typePrime, mixed $value): ?float
    {
        if ($typePrime === 'aucune') {
            return null;
        }

        if ($value === null || $value === '') {
            throw new InvalidArgumentException('La valeur de prime est requise lorsqu\'une prime est configuree.');
        }

        $normalized = $this->normalizeAmount($value, 'Valeur de la prime');

        if ($typePrime === 'actes' && $normalized > 100) {
            throw new InvalidArgumentException('Le pourcentage de prime sur actes ne peut pas depasser 100.');
        }

        return $normalized;
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
            foreach ($consultation->getFacture()?->getPaiements() as $pay) {
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
            throw new InvalidArgumentException(sprintf('Le champ "%s" doit Ãªtre un nombre.', $fieldLabel));
        }

        $amount = (float) $value;

        if ($amount < 0) {
            throw new InvalidArgumentException(sprintf('Le champ "%s" ne peut pas Ãªtre négatif.', $fieldLabel));
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
            'UNIQ_F804D3B9E7927C74' => "Cette adresse email est déjÃ  utilisée.",
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

