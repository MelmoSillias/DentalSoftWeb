<?php

namespace App\Settings\Service;

use App\Settings\Entity\AppSetting;
use App\Settings\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class GlobalSettingsService
{
    private const KEY_GENERAL = 'general';
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
        'Consultation',
        'Détartrage',
        'Extraction',
        'Remplissage',
        'Composite',
        'Amalgame',
        'Traitement de canal',
        'Traumatisme',
        'Couronne',
        'Blanchiment',
        'Radio',
        'Prothèse',
        'Orthodontie',
        'Chirurgie',
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

    public function __construct(
        private AppSettingRepository $appSettingRepo,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{autoApproveDevices: bool, requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, allowReceptionConsultationQuickActions: bool, hidePatientDossierForMedecins: bool, hidePatientPhoneForMedecins: bool, paiementDirectAssurance: bool, ficheFormSimplifie: bool, transactionMotifs: array{revenue: string[], expense: string[]}, soinsList: string[], examensTypes: string[], traitementTypes: string[], allergyTypes: string[], antecedentTypes: string[]} */
    public function getGeneralSettings(): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $value = $entry?->getValue() ?? [];
        $allowReceptionConsultationQuickActions = (bool) ($value['allowReceptionConsultationQuickActions'] ?? ($value['allowReceptionQuickCloseConsultation'] ?? true));

        return [
            'autoApproveDevices' => (bool) ($value['autoApproveDevices'] ?? true),
            'requireMedecinOnConsultationCreation' => (bool) ($value['requireMedecinOnConsultationCreation'] ?? true),
            'allowReceptionQuickCloseConsultation' => $allowReceptionConsultationQuickActions,
            'allowReceptionConsultationQuickActions' => $allowReceptionConsultationQuickActions,
            'hidePatientDossierForMedecins' => (bool) ($value['hidePatientDossierForMedecins'] ?? false),
            'hidePatientPhoneForMedecins' => (bool) ($value['hidePatientPhoneForMedecins'] ?? false),
            'paiementDirectAssurance' => (bool) ($value['paiementDirectAssurance'] ?? false),
            'ficheFormSimplifie' => (bool) ($value['ficheFormSimplifie'] ?? false),
            'transactionMotifs' => $this->sanitizeTransactionMotifs($value['transactionMotifs'] ?? null),
            'soinsList' => $this->sanitizeStringList($value['soinsList'] ?? null, self::DEFAULT_SOINS_LIST),
            'examensTypes' => $this->sanitizeStringList($value['examensTypes'] ?? null, self::DEFAULT_EXAMENS_TYPES),
            'traitementTypes' => $this->sanitizeStringList($value['traitementTypes'] ?? null, self::DEFAULT_TRAITEMENT_TYPES),
            'allergyTypes' => $this->sanitizeStringList($value['allergyTypes'] ?? null, self::DEFAULT_ALLERGY_TYPES),
            'antecedentTypes' => $this->sanitizeStringList($value['antecedentTypes'] ?? null, self::DEFAULT_ANTECEDENT_TYPES),
        ];
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
        $entry->setValue([
            ...$current,
            'autoApproveDevices' => (bool) ($payload['autoApproveDevices'] ?? ($current['autoApproveDevices'] ?? true)),
            'requireMedecinOnConsultationCreation' => (bool) ($payload['requireMedecinOnConsultationCreation'] ?? ($current['requireMedecinOnConsultationCreation'] ?? false)),
            'allowReceptionQuickCloseConsultation' => $allowReceptionConsultationQuickActions,
            'allowReceptionConsultationQuickActions' => $allowReceptionConsultationQuickActions,
            'hidePatientDossierForMedecins' => (bool) ($payload['hidePatientDossierForMedecins'] ?? ($current['hidePatientDossierForMedecins'] ?? false)),
            'hidePatientPhoneForMedecins' => (bool) ($payload['hidePatientPhoneForMedecins'] ?? ($current['hidePatientPhoneForMedecins'] ?? false)),
            'paiementDirectAssurance' => (bool) ($payload['paiementDirectAssurance'] ?? $payload['paymentDirectInsurance'] ?? ($current['paiementDirectAssurance'] ?? false)),
            'ficheFormSimplifie' => (bool) ($payload['ficheFormSimplifie'] ?? ($current['ficheFormSimplifie'] ?? false)),
            'transactionMotifs' => $this->sanitizeTransactionMotifs($payload['transactionMotifs'] ?? ($current['transactionMotifs'] ?? null)),
            'soinsList' => $this->sanitizeStringList($payload['soinsList'] ?? ($current['soinsList'] ?? null), self::DEFAULT_SOINS_LIST),
            'examensTypes' => $this->sanitizeStringList($payload['examensTypes'] ?? ($current['examensTypes'] ?? null), self::DEFAULT_EXAMENS_TYPES),
            'traitementTypes' => $this->sanitizeStringList($payload['traitementTypes'] ?? ($current['traitementTypes'] ?? null), self::DEFAULT_TRAITEMENT_TYPES),
            'allergyTypes' => $this->sanitizeStringList($payload['allergyTypes'] ?? ($current['allergyTypes'] ?? null), self::DEFAULT_ALLERGY_TYPES),
            'antecedentTypes' => $this->sanitizeStringList($payload['antecedentTypes'] ?? ($current['antecedentTypes'] ?? null), self::DEFAULT_ANTECEDENT_TYPES),
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

    public function isReceptionQuickCloseConsultationAllowed(): bool
    {
        return $this->getGeneralSettings()['allowReceptionConsultationQuickActions'];
    }

    public function isPatientDossierHiddenForMedecins(): bool
    {
        return $this->getGeneralSettings()['hidePatientDossierForMedecins'];
    }

    public function isPatientPhoneHiddenForMedecins(): bool
    {
        return $this->getGeneralSettings()['hidePatientPhoneForMedecins'];
    }

    public function isDirectInsurancePaymentEnabled(): bool
    {
        return $this->getGeneralSettings()['paiementDirectAssurance'];
    }

    /** @return array{revenue: string[], expense: string[]} */
    public function getTransactionMotifs(): array
    {
        return $this->getGeneralSettings()['transactionMotifs'];
    }

    /** @return string[] */
    public function getSoinsList(): array
    {
        return $this->getGeneralSettings()['soinsList'];
    }

    /** @return array{requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, allowReceptionConsultationQuickActions: bool, hidePatientDossierForMedecins: bool, hidePatientPhoneForMedecins: bool, paiementDirectAssurance: bool, ficheFormSimplifie: bool, soinsList: string[], examensTypes: string[], traitementTypes: string[], allergyTypes: string[], antecedentTypes: string[]} */
    public function getPublicGeneralSettings(): array
    {
        $settings = $this->getGeneralSettings();

        return [
            'requireMedecinOnConsultationCreation' => $settings['requireMedecinOnConsultationCreation'],
            'allowReceptionQuickCloseConsultation' => $settings['allowReceptionQuickCloseConsultation'],
            'allowReceptionConsultationQuickActions' => $settings['allowReceptionConsultationQuickActions'],
            'hidePatientDossierForMedecins' => $settings['hidePatientDossierForMedecins'],
            'hidePatientPhoneForMedecins' => $settings['hidePatientPhoneForMedecins'],
            'paiementDirectAssurance' => $settings['paiementDirectAssurance'],
            'ficheFormSimplifie' => $settings['ficheFormSimplifie'],
            'soinsList' => $settings['soinsList'],
            'examensTypes' => $settings['examensTypes'],
            'traitementTypes' => $settings['traitementTypes'],
            'allergyTypes' => $settings['allergyTypes'],
            'antecedentTypes' => $settings['antecedentTypes'],
        ];
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
}
