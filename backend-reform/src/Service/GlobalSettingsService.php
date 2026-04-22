<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
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

    public function __construct(
        private AppSettingRepository $appSettingRepo,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{autoApproveDevices: bool, requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, paiementDirectAssurance: bool, transactionMotifs: array{revenue: string[], expense: string[]}, soinsList: string[]} */
    public function getGeneralSettings(): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $value = $entry?->getValue() ?? [];

        return [
            'autoApproveDevices' => (bool) ($value['autoApproveDevices'] ?? true),
            'requireMedecinOnConsultationCreation' => (bool) ($value['requireMedecinOnConsultationCreation'] ?? true),
            'allowReceptionQuickCloseConsultation' => (bool) ($value['allowReceptionQuickCloseConsultation'] ?? true),
            'paiementDirectAssurance' => (bool) ($value['paiementDirectAssurance'] ?? false),
            'transactionMotifs' => $this->sanitizeTransactionMotifs($value['transactionMotifs'] ?? null),
            'soinsList' => $this->sanitizeStringList($value['soinsList'] ?? null, self::DEFAULT_SOINS_LIST),
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
        $entry->setValue([
            ...$current,
            'autoApproveDevices' => (bool) ($payload['autoApproveDevices'] ?? ($current['autoApproveDevices'] ?? true)),
            'requireMedecinOnConsultationCreation' => (bool) ($payload['requireMedecinOnConsultationCreation'] ?? ($current['requireMedecinOnConsultationCreation'] ?? false)),
            'allowReceptionQuickCloseConsultation' => (bool) ($payload['allowReceptionQuickCloseConsultation'] ?? ($current['allowReceptionQuickCloseConsultation'] ?? true)),
            'paiementDirectAssurance' => (bool) ($payload['paiementDirectAssurance'] ?? $payload['paymentDirectInsurance'] ?? ($current['paiementDirectAssurance'] ?? false)),
            'transactionMotifs' => $this->sanitizeTransactionMotifs($payload['transactionMotifs'] ?? ($current['transactionMotifs'] ?? null)),
            'soinsList' => $this->sanitizeStringList($payload['soinsList'] ?? ($current['soinsList'] ?? null), self::DEFAULT_SOINS_LIST),
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
        return $this->getGeneralSettings()['allowReceptionQuickCloseConsultation'];
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

    /** @return array{requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, paiementDirectAssurance: bool, soinsList: string[]} */
    public function getPublicGeneralSettings(): array
    {
        $settings = $this->getGeneralSettings();

        return [
            'requireMedecinOnConsultationCreation' => $settings['requireMedecinOnConsultationCreation'],
            'allowReceptionQuickCloseConsultation' => $settings['allowReceptionQuickCloseConsultation'],
            'paiementDirectAssurance' => $settings['paiementDirectAssurance'],
            'soinsList' => $settings['soinsList'],
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
