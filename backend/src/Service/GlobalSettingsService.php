<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class GlobalSettingsService
{
    private const KEY_GENERAL = 'general';

    public function __construct(
        private AppSettingRepository $appSettingRepo,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{autoApproveDevices: bool, requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, paiementDirectAssurance: bool} */
    public function getGeneralSettings(): array
    {
        $entry = $this->appSettingRepo->findOneByKey(self::KEY_GENERAL);
        $value = $entry?->getValue() ?? [];

        return [
            'autoApproveDevices' => (bool) ($value['autoApproveDevices'] ?? true),
            'requireMedecinOnConsultationCreation' => (bool) ($value['requireMedecinOnConsultationCreation'] ?? true),
            'allowReceptionQuickCloseConsultation' => (bool) ($value['allowReceptionQuickCloseConsultation'] ?? true),
            'paiementDirectAssurance' => (bool) ($value['paiementDirectAssurance'] ?? false),
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

    /** @return array{requireMedecinOnConsultationCreation: bool, allowReceptionQuickCloseConsultation: bool, paiementDirectAssurance: bool} */
    public function getPublicGeneralSettings(): array
    {
        $settings = $this->getGeneralSettings();

        return [
            'requireMedecinOnConsultationCreation' => $settings['requireMedecinOnConsultationCreation'],
            'allowReceptionQuickCloseConsultation' => $settings['allowReceptionQuickCloseConsultation'],
            'paiementDirectAssurance' => $settings['paiementDirectAssurance'],
        ];
    }
}
