<?php

namespace App\Settings\Service;

use App\IdentityAccess\Entity\User;
use App\Settings\Entity\AppSetting;
use App\Settings\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class GlobalSettingsService
{
    private const KEY_GENERAL = 'general';
    private const TEST_MODE_ENABLED_KEY = 'testModeEnabled';
    private const TEST_MODE_SNAPSHOT_PATH_KEY = 'testModeSnapshotPath';
    private const TEST_MODE_SNAPSHOT_CREATED_AT_KEY = 'testModeSnapshotCreatedAt';
    private const TEST_MODE_LAST_PURGE_AT_KEY = 'testModeLastPurgeAt';
    private const DEFAULT_CONSULTATION_PRICE = 5000.0;
    private const DEFAULT_CONSULTATION_PRICES = [5000.0];
    private const DEFAULT_OPENING_TIME = '08:00';
    private const DEFAULT_CLOSING_TIME = '18:00';
    private const DEFAULT_TRANSACTION_MOTIFS = [
        'revenue' => [
            'Paiement patient',
            'Remboursement assurance',
            'Vente produit',
            'Autre',
        ],
        'expense' => [
            'Achat matériel',
            'Frais généraux',
            'Paiement salaire',
            'Maintenance',
            'Autre',
        ],
    ];
    private const DEFAULT_SOINS_LIST = [
        ['description' => 'Consultation', 'montant' => 0.0],
        ['description' => 'Détartrage', 'montant' => 0.0],
        ['description' => 'Extraction', 'montant' => 0.0],
        ['description' => 'Remplissage', 'montant' => 0.0],
        ['description' => 'Composite', 'montant' => 0.0],
        ['description' => 'Amalgame', 'montant' => 0.0],
        ['description' => 'Traitement de canal', 'montant' => 0.0],
        ['description' => 'Traumatisme', 'montant' => 0.0],
        ['description' => 'Couronne', 'montant' => 0.0],
        ['description' => 'Blanchiment', 'montant' => 0.0],
        ['description' => 'Radio', 'montant' => 0.0],
        ['description' => 'Prothèse', 'montant' => 0.0],
        ['description' => 'Orthodontie', 'montant' => 0.0],
        ['description' => 'Chirurgie', 'montant' => 0.0],
    ];
    private const DEFAULT_EXAMENS_TYPES = [
        'Bacteriologique',
        'Serologique',
        'Histologique',
        'Radiologique',
        'Autre',
    ];
    private const DEFAULT_TRAITEMENT_TYPES = [
        'Urgence',
        'Dentaires',
        'Parodontaux',
        'Orthodontiques',
        'Autres',
    ];
    private const DEFAULT_ALLERGY_TYPES = [
        'Médicamenteuses',
        'Alimentaires',
        'Environnementales',
        'Autres',
    ];
    private const DEFAULT_ANTECEDENT_TYPES = [
        'Personnel',
        'Familial',
        'Médical',
    ];
    private const DEFAULT_PATIENT_PORTAL_CLOSED_MESSAGE = 'Le portail patient est temporairement indisponible. Merci de contacter le cabinet pour toute assistance.';
    private const DEFAULT_SMS_CABINET_NAME = 'Cabinet dentaire';

    public function __construct(
        private AppSettingRepository $appSettingRepo,
        private EntityManagerInterface $em,
        private DatabaseMaintenanceService $databaseMaintenanceService,
        private InternetFeaturesGate $internetFeaturesGate,
    ) {
    }

    /** @return array{autoApproveDevices: bool, requireMedecinOnConsultationCreation: bool, defaultCreateConsultationOnRdvValidation: bool, allowReceptionQuickCloseConsultation: bool, allowReceptionConsultationQuickActions: bool, showReceptionQuickCloseButton: bool, allowReceptionBypassMedecinPasswordOnQuickClose: bool, hidePatientDossierForMedecins: bool, hidePatientPhoneForMedecins: bool, ficheFormSimplifie: bool, showDiagnosticPositifInConsultation: bool, consultationPrice: float, consultationPrices: float[], transactionMotifs: array{revenue: string[], expense: string[]}, soinsList: list<array{description: string, montant: float}>, examensTypes: string[], traitementTypes: string[], allergyTypes: string[], antecedentTypes: string[], patientPortalEnabled: bool, patientPortalClosedMessage: string, patientPortalBaseUrl: ?string, cabinetShowcaseWebsiteUrl: ?string, smsCabinetName: string, autoCreatePortalAccountOnPatientCreation: bool} */
    public function getGeneralSettings(): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $value = $entry?->getValue() ?? [];
        $allowReceptionConsultationQuickActions = (bool) ($value['allowReceptionConsultationQuickActions'] ?? ($value['allowReceptionQuickCloseConsultation'] ?? true));
        $showReceptionQuickCloseButton = (bool) ($value['showReceptionQuickCloseButton'] ?? true);

        $result = [
            'autoApproveDevices' => (bool) ($value['autoApproveDevices'] ?? true),
            'requireMedecinOnConsultationCreation' => (bool) ($value['requireMedecinOnConsultationCreation'] ?? true),
            'defaultCreateConsultationOnRdvValidation' => (bool) ($value['defaultCreateConsultationOnRdvValidation'] ?? false),
            'allowReceptionQuickCloseConsultation' => $allowReceptionConsultationQuickActions,
            'allowReceptionConsultationQuickActions' => $allowReceptionConsultationQuickActions,
            'showReceptionQuickCloseButton' => $showReceptionQuickCloseButton,
            'allowReceptionBypassMedecinPasswordOnQuickClose' => $showReceptionQuickCloseButton && (bool) ($value['allowReceptionBypassMedecinPasswordOnQuickClose'] ?? false),
            'allowReceptionInvoiceModification' => (bool) ($value['allowReceptionInvoiceModification'] ?? false),
            'allowConsultationPriceEditOnCreation' => (bool) ($value['allowConsultationPriceEditOnCreation'] ?? false),
            'hidePatientDossierForMedecins' => (bool) ($value['hidePatientDossierForMedecins'] ?? false),
            'hidePatientPhoneForMedecins' => (bool) ($value['hidePatientPhoneForMedecins'] ?? false),
            'ficheFormSimplifie' => (bool) ($value['ficheFormSimplifie'] ?? false),
            'showDiagnosticPositifInConsultation' => (bool) ($value['showDiagnosticPositifInConsultation'] ?? true),
            'consultationPrice' => $this->sanitizePositiveAmount($value['consultationPrice'] ?? null, self::DEFAULT_CONSULTATION_PRICE),
            'openingTime' => $this->sanitizeTimeOfDay($value['openingTime'] ?? null, self::DEFAULT_OPENING_TIME),
            'closingTime' => $this->sanitizeTimeOfDay($value['closingTime'] ?? null, self::DEFAULT_CLOSING_TIME),
            'transactionMotifs' => $this->sanitizeTransactionMotifs($value['transactionMotifs'] ?? null),
            'soinsList' => $this->sanitizeSoinsList($value['soinsList'] ?? null),
            'examensTypes' => $this->sanitizeStringList($value['examensTypes'] ?? null, self::DEFAULT_EXAMENS_TYPES),
            'traitementTypes' => $this->sanitizeStringList($value['traitementTypes'] ?? null, self::DEFAULT_TRAITEMENT_TYPES),
            'allergyTypes' => $this->sanitizeStringList($value['allergyTypes'] ?? null, self::DEFAULT_ALLERGY_TYPES),
            'antecedentTypes' => $this->sanitizeStringList($value['antecedentTypes'] ?? null, self::DEFAULT_ANTECEDENT_TYPES),
            'patientPortalEnabled' => (bool) ($value['patientPortalEnabled'] ?? true),
            'patientPortalClosedMessage' => $this->sanitizeFreeText(
                $value['patientPortalClosedMessage'] ?? null,
                self::DEFAULT_PATIENT_PORTAL_CLOSED_MESSAGE,
                500
            ),
            'patientPortalBaseUrl' => $this->sanitizeUrl($value['patientPortalBaseUrl'] ?? null),
            'cabinetShowcaseWebsiteUrl' => $this->sanitizeUrl($value['cabinetShowcaseWebsiteUrl'] ?? null),
            'smsCabinetName' => $this->sanitizeSmsCabinetName($value['smsCabinetName'] ?? null),
            'autoCreatePortalAccountOnPatientCreation' => (bool) ($value['autoCreatePortalAccountOnPatientCreation'] ?? false),
            'testModeEnabled' => (bool) ($value[self::TEST_MODE_ENABLED_KEY] ?? false),
            'testModeSnapshotCreatedAt' => $value[self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY] ?? null,
            'testModeLastPurgeAt' => $value[self::TEST_MODE_LAST_PURGE_AT_KEY] ?? null,
        ];

        [$openingTime, $closingTime] = $this->normalizeOpeningHoursPair(
            $result['openingTime'],
            $result['closingTime']
        );
        $result['openingTime'] = $openingTime;
        $result['closingTime'] = $closingTime;

        [$consultationPrice, $consultationPrices] = $this->normalizeConsultationPrices(
            $result['consultationPrice'],
            $value['consultationPrices'] ?? null
        );
        $result['consultationPrice'] = $consultationPrice;
        $result['consultationPrices'] = $consultationPrices;

        return $result;
    }

    /** @param array<string,mixed> $payload */
    public function saveGeneralSettings(array $payload): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        if (!$entry) {
            $entry = (new AppSetting())->setKeyName(self::KEY_GENERAL);
            $this->em->persist($entry);
        }

        $current = $entry->getValue();
        $allowReceptionConsultationQuickActions = (bool) ($payload['allowReceptionConsultationQuickActions'] ?? ($payload['allowReceptionQuickCloseConsultation'] ?? ($current['allowReceptionConsultationQuickActions'] ?? ($current['allowReceptionQuickCloseConsultation'] ?? true))));
        $showReceptionQuickCloseButton = (bool) ($payload['showReceptionQuickCloseButton'] ?? ($current['showReceptionQuickCloseButton'] ?? true));
        [$openingTime, $closingTime] = $this->normalizeOpeningHoursPair(
            $this->sanitizeTimeOfDay($payload['openingTime'] ?? ($current['openingTime'] ?? null), self::DEFAULT_OPENING_TIME),
            $this->sanitizeTimeOfDay($payload['closingTime'] ?? ($current['closingTime'] ?? null), self::DEFAULT_CLOSING_TIME)
        );
        unset($current['paiementDirectAssurance'], $current['paymentDirectInsurance']);
        $entry->setValue([
            ...$current,
            'autoApproveDevices' => (bool) ($payload['autoApproveDevices'] ?? ($current['autoApproveDevices'] ?? true)),
            'requireMedecinOnConsultationCreation' => (bool) ($payload['requireMedecinOnConsultationCreation'] ?? ($current['requireMedecinOnConsultationCreation'] ?? false)),
            'defaultCreateConsultationOnRdvValidation' => (bool) ($payload['defaultCreateConsultationOnRdvValidation'] ?? ($current['defaultCreateConsultationOnRdvValidation'] ?? false)),
            'allowReceptionQuickCloseConsultation' => $allowReceptionConsultationQuickActions,
            'allowReceptionConsultationQuickActions' => $allowReceptionConsultationQuickActions,
            'showReceptionQuickCloseButton' => $showReceptionQuickCloseButton,
            'allowReceptionBypassMedecinPasswordOnQuickClose' => $showReceptionQuickCloseButton && (bool) ($payload['allowReceptionBypassMedecinPasswordOnQuickClose'] ?? ($current['allowReceptionBypassMedecinPasswordOnQuickClose'] ?? false)),
            'allowReceptionInvoiceModification' => (bool) ($payload['allowReceptionInvoiceModification'] ?? ($current['allowReceptionInvoiceModification'] ?? false)),
            'allowConsultationPriceEditOnCreation' => (bool) ($payload['allowConsultationPriceEditOnCreation'] ?? ($current['allowConsultationPriceEditOnCreation'] ?? false)),
            'hidePatientDossierForMedecins' => (bool) ($payload['hidePatientDossierForMedecins'] ?? ($current['hidePatientDossierForMedecins'] ?? false)),
            'hidePatientPhoneForMedecins' => (bool) ($payload['hidePatientPhoneForMedecins'] ?? ($current['hidePatientPhoneForMedecins'] ?? false)),
            'ficheFormSimplifie' => (bool) ($payload['ficheFormSimplifie'] ?? ($current['ficheFormSimplifie'] ?? false)),
            'showDiagnosticPositifInConsultation' => (bool) ($payload['showDiagnosticPositifInConsultation'] ?? ($current['showDiagnosticPositifInConsultation'] ?? true)),
            'consultationPrice' => $this->sanitizePositiveAmount($payload['consultationPrice'] ?? ($current['consultationPrice'] ?? null), self::DEFAULT_CONSULTATION_PRICE),
            'openingTime' => $openingTime,
            'closingTime' => $closingTime,
            'transactionMotifs' => $this->sanitizeTransactionMotifs($payload['transactionMotifs'] ?? ($current['transactionMotifs'] ?? null)),
            'soinsList' => $this->sanitizeSoinsList($payload['soinsList'] ?? ($current['soinsList'] ?? null)),
            'examensTypes' => $this->sanitizeStringList($payload['examensTypes'] ?? ($current['examensTypes'] ?? null), self::DEFAULT_EXAMENS_TYPES),
            'traitementTypes' => $this->sanitizeStringList($payload['traitementTypes'] ?? ($current['traitementTypes'] ?? null), self::DEFAULT_TRAITEMENT_TYPES),
            'allergyTypes' => $this->sanitizeStringList($payload['allergyTypes'] ?? ($current['allergyTypes'] ?? null), self::DEFAULT_ALLERGY_TYPES),
            'antecedentTypes' => $this->sanitizeStringList($payload['antecedentTypes'] ?? ($current['antecedentTypes'] ?? null), self::DEFAULT_ANTECEDENT_TYPES),
            'patientPortalEnabled' => (bool) ($payload['patientPortalEnabled'] ?? ($current['patientPortalEnabled'] ?? true)),
            'patientPortalClosedMessage' => $this->sanitizeFreeText(
                $payload['patientPortalClosedMessage'] ?? ($current['patientPortalClosedMessage'] ?? null),
                self::DEFAULT_PATIENT_PORTAL_CLOSED_MESSAGE,
                500
            ),
            'patientPortalBaseUrl' => $this->sanitizeUrl($payload['patientPortalBaseUrl'] ?? ($current['patientPortalBaseUrl'] ?? null)),
            'cabinetShowcaseWebsiteUrl' => $this->sanitizeUrl($payload['cabinetShowcaseWebsiteUrl'] ?? ($current['cabinetShowcaseWebsiteUrl'] ?? null)),
            'smsCabinetName' => $this->sanitizeSmsCabinetName($payload['smsCabinetName'] ?? ($current['smsCabinetName'] ?? null)),
            'autoCreatePortalAccountOnPatientCreation' => (bool) ($payload['autoCreatePortalAccountOnPatientCreation'] ?? ($current['autoCreatePortalAccountOnPatientCreation'] ?? false)),
        ]);

        $saved = $entry->getValue();
        [$consultationPrice, $consultationPrices] = $this->normalizeConsultationPrices(
            $saved['consultationPrice'] ?? self::DEFAULT_CONSULTATION_PRICE,
            array_key_exists('consultationPrices', $payload)
                ? $payload['consultationPrices']
                : ($current['consultationPrices'] ?? null)
        );
        $entry->setValue([
            ...$saved,
            'consultationPrice' => $consultationPrice,
            'consultationPrices' => $consultationPrices,
        ]);

        $this->em->flush();

        return $this->getGeneralSettings();
    }

    public function isAutoApproveDevicesEnabled(): bool
    {
        return $this->getGeneralSettings()['autoApproveDevices'];
    }

    public function isMedecinRequiredOnConsultationCreation(): bool
    {
        return $this->getGeneralSettings()['requireMedecinOnConsultationCreation'];
    }

    public function isDefaultCreateConsultationOnRdvValidation(): bool
    {
        return $this->getGeneralSettings()['defaultCreateConsultationOnRdvValidation'];
    }

    public function isReceptionQuickCloseConsultationAllowed(): bool
    {
        $settings = $this->getGeneralSettings();

        return $settings['allowReceptionConsultationQuickActions'] && $settings['showReceptionQuickCloseButton'];
    }

    public function canReceptionBypassMedecinPasswordOnQuickClose(): bool
    {
        return $this->getGeneralSettings()['allowReceptionBypassMedecinPasswordOnQuickClose'];
    }

    public function isReceptionInvoiceModificationAllowed(): bool
    {
        return $this->getGeneralSettings()['allowReceptionInvoiceModification'];
    }

    public function isConsultationPriceEditableOnCreation(): bool
    {
        return $this->getGeneralSettings()['allowConsultationPriceEditOnCreation'];
    }

    public function isPatientDossierHiddenForMedecins(): bool
    {
        return $this->getGeneralSettings()['hidePatientDossierForMedecins'];
    }

    public function isPatientPhoneHiddenForMedecins(): bool
    {
        return $this->getGeneralSettings()['hidePatientPhoneForMedecins'];
    }

    public function getTestModeStatus(): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $value = $entry?->getValue() ?? [];

        return [
            'testModeEnabled' => (bool) ($value[self::TEST_MODE_ENABLED_KEY] ?? false),
            'testModeSnapshotCreatedAt' => $value[self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY] ?? null,
            'testModeLastPurgeAt' => $value[self::TEST_MODE_LAST_PURGE_AT_KEY] ?? null,
        ];
    }

    public function toggleTestMode(bool $enabled, User $admin, string $password, bool $deleteTestData = true): array
    {
        if (!$this->databaseMaintenanceService->verifyAdminPassword($admin, $password)) {
            throw new \InvalidArgumentException('Mot de passe admin invalide.');
        }

        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        if (!$entry) {
            $entry = (new AppSetting())->setKeyName(self::KEY_GENERAL);
            $this->em->persist($entry);
        }

        $current = $entry->getValue();
        $currentlyEnabled = (bool) ($current[self::TEST_MODE_ENABLED_KEY] ?? false);

        if ($enabled && !$currentlyEnabled) {
            $backup = $this->databaseMaintenanceService->createBackup(['sql', 'zip'], 'test_snapshot');

            $entry->setValue([
                ...$current,
                self::TEST_MODE_ENABLED_KEY => true,
                self::TEST_MODE_SNAPSHOT_PATH_KEY => $backup['relativeSqlPath'],
                self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY => $backup['createdAt'],
            ]);
            $this->em->flush();

            return [
                ...$this->getTestModeStatus(),
                'message' => 'Mode test activé. Snapshot initial créé.',
                'snapshot' => $backup,
            ];
        }

        if (!$enabled && $currentlyEnabled) {
            $snapshotPath = (string) ($current[self::TEST_MODE_SNAPSHOT_PATH_KEY] ?? '');

            if ($deleteTestData) {
                if ($snapshotPath === '') {
                    throw new \RuntimeException('Snapshot du mode test introuvable.');
                }

                $this->databaseMaintenanceService->restoreSqlBackup($snapshotPath);

                $entryAfterRestore = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
                if (!$entryAfterRestore) {
                    $entryAfterRestore = (new AppSetting())->setKeyName(self::KEY_GENERAL);
                    $this->em->persist($entryAfterRestore);
                }

                $restored = $entryAfterRestore->getValue();
                $entryAfterRestore->setValue([
                    ...$restored,
                    self::TEST_MODE_ENABLED_KEY => false,
                    self::TEST_MODE_SNAPSHOT_PATH_KEY => $snapshotPath,
                    self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY => $current[self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY] ?? null,
                    self::TEST_MODE_LAST_PURGE_AT_KEY => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]);

                $this->em->flush();

                return [
                    ...$this->getTestModeStatus(),
                    'message' => 'Mode test désactivé. Données test supprimées via restauration snapshot.',
                ];
            }

            $entry->setValue([
                ...$current,
                self::TEST_MODE_ENABLED_KEY => false,
            ]);
            $this->em->flush();

            return [
                ...$this->getTestModeStatus(),
                'message' => 'Mode test désactivé. Les données actuelles ont été conservées.',
            ];
        }

        return [
            ...$this->getTestModeStatus(),
            'message' => $enabled ? 'Mode test déjà actif.' : 'Mode test déjà inactif.',
        ];
    }

    public function cleanTestModeData(User $admin, string $password): array
    {
        if (!$this->databaseMaintenanceService->verifyAdminPassword($admin, $password)) {
            throw new \InvalidArgumentException('Mot de passe admin invalide.');
        }

        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $current = $entry?->getValue() ?? [];
        $enabled = (bool) ($current[self::TEST_MODE_ENABLED_KEY] ?? false);

        if (!$enabled) {
            throw new \InvalidArgumentException('Le mode test doit être actif pour nettoyer les tests.');
        }

        $snapshotPath = (string) ($current[self::TEST_MODE_SNAPSHOT_PATH_KEY] ?? '');
        if ($snapshotPath === '') {
            throw new \RuntimeException('Snapshot du mode test introuvable.');
        }

        $this->databaseMaintenanceService->restoreSqlBackup($snapshotPath);

        $newSnapshot = $this->databaseMaintenanceService->createBackup(['sql', 'zip'], 'test_snapshot');
        $entryAfterRestore = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        if (!$entryAfterRestore) {
            $entryAfterRestore = (new AppSetting())->setKeyName(self::KEY_GENERAL);
            $this->em->persist($entryAfterRestore);
        }

        $restored = $entryAfterRestore->getValue();
        $entryAfterRestore->setValue([
            ...$restored,
            self::TEST_MODE_ENABLED_KEY => true,
            self::TEST_MODE_SNAPSHOT_PATH_KEY => $newSnapshot['relativeSqlPath'],
            self::TEST_MODE_SNAPSHOT_CREATED_AT_KEY => $newSnapshot['createdAt'],
            self::TEST_MODE_LAST_PURGE_AT_KEY => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
        $this->em->flush();

        return [
            ...$this->getTestModeStatus(),
            'message' => 'Nettoyage de test effectué avec succès.',
            'snapshot' => $newSnapshot,
        ];
    }

    public function getConsultationPrice(): float
    {
        return $this->getGeneralSettings()['consultationPrice'];
    }

    /** @return float[] */
    public function getConsultationPrices(): array
    {
        return $this->getGeneralSettings()['consultationPrices'];
    }

    /** @return array{revenue: string[], expense: string[]} */
    public function getTransactionMotifs(): array
    {
        return $this->getGeneralSettings()['transactionMotifs'];
    }

    /** @return list<array{description: string, montant: float}> */
    public function getSoinsList(): array
    {
        return $this->getGeneralSettings()['soinsList'];
    }

    public function isPatientPortalEnabled(): bool
    {
        return $this->getGeneralSettings()['patientPortalEnabled'];
    }

    public function getPatientPortalClosedMessage(): string
    {
        return $this->getGeneralSettings()['patientPortalClosedMessage'];
    }

    public function shouldAutoCreatePortalAccountOnPatientCreation(): bool
    {
        return $this->getGeneralSettings()['autoCreatePortalAccountOnPatientCreation'];
    }

    public function getSmsCabinetName(): string
    {
        return $this->getGeneralSettings()['smsCabinetName'];
    }

    private const STAFF_ROLES = ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'];

    /** @param string[] $roles */
    public function getPublicGeneralSettingsForRoles(array $roles): array
    {
        if ($this->hasStaffRole($roles)) {
            return $this->getStaffOperationalSettings();
        }

        return $this->getPortalPublicSettings();
    }

    /** @return array{patientPortalEnabled: bool, patientPortalClosedMessage: string, patientPortalBaseUrl: ?string, cabinetShowcaseWebsiteUrl: ?string} */
    public function getPortalPublicSettings(): array
    {
        $settings = $this->getGeneralSettings();

        return [
            'patientPortalEnabled' => $settings['patientPortalEnabled'],
            'patientPortalClosedMessage' => $settings['patientPortalClosedMessage'],
            'patientPortalBaseUrl' => $settings['patientPortalBaseUrl'],
            'cabinetShowcaseWebsiteUrl' => $settings['cabinetShowcaseWebsiteUrl'],
        ];
    }

    /** @return array{requireMedecinOnConsultationCreation: bool, defaultCreateConsultationOnRdvValidation: bool, allowReceptionQuickCloseConsultation: bool, allowReceptionConsultationQuickActions: bool, showReceptionQuickCloseButton: bool, allowReceptionBypassMedecinPasswordOnQuickClose: bool, hidePatientDossierForMedecins: bool, hidePatientPhoneForMedecins: bool, ficheFormSimplifie: bool, showDiagnosticPositifInConsultation: bool, consultationPrice: float, consultationPrices: float[], soinsList: list<array{description: string, montant: float}>, examensTypes: string[], traitementTypes: string[], allergyTypes: string[], antecedentTypes: string[], patientPortalEnabled: bool, patientPortalClosedMessage: string, patientPortalBaseUrl: ?string, cabinetShowcaseWebsiteUrl: ?string, smsCabinetName: string} */
    public function getStaffOperationalSettings(): array
    {
        $settings = $this->getGeneralSettings();

        return [
            'requireMedecinOnConsultationCreation' => $settings['requireMedecinOnConsultationCreation'],
            'defaultCreateConsultationOnRdvValidation' => $settings['defaultCreateConsultationOnRdvValidation'],
            'allowReceptionQuickCloseConsultation' => $settings['allowReceptionQuickCloseConsultation'] && $settings['showReceptionQuickCloseButton'],
            'allowReceptionConsultationQuickActions' => $settings['allowReceptionConsultationQuickActions'],
            'showReceptionQuickCloseButton' => $settings['showReceptionQuickCloseButton'],
            'allowReceptionBypassMedecinPasswordOnQuickClose' => $settings['allowReceptionBypassMedecinPasswordOnQuickClose'],
            'allowReceptionInvoiceModification' => $settings['allowReceptionInvoiceModification'],
            'allowConsultationPriceEditOnCreation' => $settings['allowConsultationPriceEditOnCreation'],
            'hidePatientDossierForMedecins' => $settings['hidePatientDossierForMedecins'],
            'hidePatientPhoneForMedecins' => $settings['hidePatientPhoneForMedecins'],
            'ficheFormSimplifie' => $settings['ficheFormSimplifie'],
            'showDiagnosticPositifInConsultation' => $settings['showDiagnosticPositifInConsultation'],
            'consultationPrice' => $settings['consultationPrice'],
            'consultationPrices' => $settings['consultationPrices'],
            'openingTime' => $settings['openingTime'],
            'closingTime' => $settings['closingTime'],
            'soinsList' => $settings['soinsList'],
            'examensTypes' => $settings['examensTypes'],
            'traitementTypes' => $settings['traitementTypes'],
            'allergyTypes' => $settings['allergyTypes'],
            'antecedentTypes' => $settings['antecedentTypes'],
            'patientPortalEnabled' => $settings['patientPortalEnabled'],
            'patientPortalClosedMessage' => $settings['patientPortalClosedMessage'],
            'patientPortalBaseUrl' => $settings['patientPortalBaseUrl'],
            'cabinetShowcaseWebsiteUrl' => $settings['cabinetShowcaseWebsiteUrl'],
            'smsCabinetName' => $settings['smsCabinetName'],
            'internetFeaturesEnabled' => $this->internetFeaturesGate->isEnabled(),
        ];
    }

    /** @param string[] $roles */
    private function hasStaffRole(array $roles): bool
    {
        return (bool) array_intersect(self::STAFF_ROLES, $roles);
    }

    /** @return array{revenue: string[], expense: string[]} */
    private function sanitizeTransactionMotifs(mixed $value): array
    {
        $fallback = self::DEFAULT_TRANSACTION_MOTIFS;
        $source = is_array($value) ? $value : [];

        $normalize = static function (mixed $items, array $defaultItems): array {
            if (!is_array($items)) {
                return $defaultItems;
            }

            $clean = [];
            foreach ($items as $item) {
                if (!is_scalar($item)) {
                    continue;
                }

                $label = trim((string) $item);
                if ($label === '') {
                    continue;
                }

                $clean[$label] = $label;
            }

            return array_values($clean ?: array_combine($defaultItems, $defaultItems));
        };

        return [
            'revenue' => $normalize($source['revenue'] ?? null, $fallback['revenue']),
            'expense' => $normalize($source['expense'] ?? null, $fallback['expense']),
        ];
    }

    /** @return string[] */
    private function sanitizeStringList(mixed $value, array $defaultItems): array
    {
        if (!is_array($value)) {
            return $defaultItems;
        }

        $clean = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $label = trim((string) $item);
            if ($label === '') {
                continue;
            }

            $clean[$label] = $label;
        }

        return array_values($clean ?: array_combine($defaultItems, $defaultItems));
    }

    /**
     * @return list<array{description: string, montant: float}>
     */
    private function sanitizeSoinsList(mixed $value): array
    {
        if (!is_array($value)) {
            return self::DEFAULT_SOINS_LIST;
        }

        $clean = [];
        foreach ($value as $item) {
            $description = '';
            $montant = 0.0;

            if (is_scalar($item)) {
                $description = trim((string) $item);
            } elseif (is_array($item)) {
                $rawDescription = $item['description'] ?? $item['label'] ?? $item['type'] ?? '';
                $description = is_scalar($rawDescription) ? trim((string) $rawDescription) : '';
                $rawMontant = $item['montant'] ?? $item['prix'] ?? 0;
                $montant = is_numeric($rawMontant) ? max(0.0, (float) $rawMontant) : 0.0;
            } else {
                continue;
            }

            if ($description === '' || isset($clean[$description])) {
                continue;
            }

            $clean[$description] = [
                'description' => $description,
                'montant' => round($montant, 2),
            ];
        }

        return array_values($clean ?: self::DEFAULT_SOINS_LIST);
    }

    private function sanitizePositiveAmount(mixed $value, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $amount = (float) $value;
        if ($amount <= 0) {
            return $default;
        }

        return round($amount, 2);
    }

    /**
     * @return array{0: float, 1: float[]}
     */
    private function normalizeConsultationPrices(mixed $defaultPrice, mixed $prices): array
    {
        $default = $this->sanitizePositiveAmount($defaultPrice, self::DEFAULT_CONSULTATION_PRICE);
        $source = is_array($prices) ? $prices : [];
        $clean = [];

        foreach ($source as $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $amount = round((float) $item, 2);
            if ($amount <= 0) {
                continue;
            }

            $key = number_format($amount, 2, '.', '');
            $clean[$key] = $amount;
        }

        if ($clean === []) {
            foreach (self::DEFAULT_CONSULTATION_PRICES as $fallbackAmount) {
                $fallbackKey = number_format($fallbackAmount, 2, '.', '');
                $clean[$fallbackKey] = $fallbackAmount;
            }
            $clean[number_format($default, 2, '.', '')] = $default;
        }

        $defaultKey = number_format($default, 2, '.', '');
        if (!isset($clean[$defaultKey])) {
            $clean = [$defaultKey => $default] + $clean;
        }

        $list = array_values($clean);
        usort($list, static fn (float $a, float $b): int => $a <=> $b);

        return [$default, $list];
    }

    private function sanitizeTimeOfDay(mixed $value, string $default): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return $default;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $matches)) {
            return $default;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return $default;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /** @return array{0: string, 1: string} */
    private function normalizeOpeningHoursPair(string $openingTime, string $closingTime): array
    {
        if ($openingTime < $closingTime) {
            return [$openingTime, $closingTime];
        }

        return [self::DEFAULT_OPENING_TIME, self::DEFAULT_CLOSING_TIME];
    }

    private function sanitizeUrl(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = rtrim($raw, '/');
        if (!preg_match('/^https?:\/\//i', $normalized)) {
            return null;
        }

        return filter_var($normalized, FILTER_VALIDATE_URL) ? $normalized : null;
    }

    private function sanitizeFreeText(mixed $value, string $fallback, int $maxLength): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text;
    }

    private function sanitizeSmsCabinetName(mixed $value): string
    {
        return $this->sanitizeFreeText($value, self::DEFAULT_SMS_CABINET_NAME, 120);
    }
}
