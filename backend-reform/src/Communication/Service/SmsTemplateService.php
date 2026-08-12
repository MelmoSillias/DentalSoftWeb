<?php

namespace App\Communication\Service;

use App\Communication\Entity\SmsTemplate;
use App\Communication\Repository\SmsTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SmsTemplateService
{
    /**
     * @return array<string, array{name: string, type: string, content: string}>
     */
    private function defaults(): array
    {
        return [
            'patient_created' => [
                'name' => 'Accusé création patient',
                'type' => 'receipt',
                'content' => "Bonjour {patient_name},\nVotre dossier a été créé chez {cabinet_name}.",
            ],
            'receipt' => [
                'name' => 'Reçu paiement',
                'type' => 'receipt',
                'content' => "Merci {patient_name}.\nPaiement reçu : {amount} FCFA\n{cabinet_name}.",
            ],
            'invoice' => [
                'name' => 'Facture',
                'type' => 'invoice',
                'content' => "Facture {invoice_number}\nMontant : {amount} FCFA\n{cabinet_name}",
            ],
            'appointment_reminder' => [
                'name' => 'Rappel rendez-vous',
                'type' => 'appointment reminder',
                'content' => "Rappel : rendez-vous le {date} à {time}\n{cabinet_name}",
            ],
            'ticket' => [
                'name' => 'Ticket consultation',
                'type' => 'ticket',
                'content' => "Bonjour {patient_name}, ticket de consultation enregistré le {date}.\n{cabinet_name}",
            ],
        ];
    }

    public function __construct(
        private readonly SmsTemplateRepository $templateRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureDefaults(): void
    {
        $existing = $this->templateRepository->findAll();
        $index = [];
        foreach ($existing as $template) {
            $index[$template->getCode()] = $template;
        }

        $changed = false;
        foreach ($this->defaults() as $code => $tpl) {
            if (isset($index[$code])) {
                continue;
            }

            $template = (new SmsTemplate())
                ->setCode($code)
                ->setName($tpl['name'])
                ->setType($tpl['type'])
                ->setContent($tpl['content'])
                ->setEnabled(true)
                ->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($template);
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        $this->ensureDefaults();
        $templates = $this->templateRepository->findBy([], ['name' => 'ASC']);

        return array_map(static function (SmsTemplate $template): array {
            return [
                'id' => $template->getId(),
                'code' => $template->getCode(),
                'name' => $template->getName(),
                'type' => $template->getType(),
                'content' => $template->getContent(),
                'enabled' => $template->isEnabled(),
                'updatedAt' => $template->getUpdatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $templates);
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     */
    public function saveTemplates(array $templates): void
    {
        $this->ensureDefaults();
        $all = $this->templateRepository->findAll();
        $index = [];
        foreach ($all as $template) {
            $index[$template->getCode()] = $template;
        }

        foreach ($templates as $entry) {
            $code = isset($entry['code']) ? trim((string) $entry['code']) : '';
            if ($code === '' || !isset($index[$code])) {
                continue;
            }

            $template = $index[$code];
            $template
                ->setName(trim((string) ($entry['name'] ?? $template->getName())))
                ->setType(trim((string) ($entry['type'] ?? $template->getType())))
                ->setContent((string) ($entry['content'] ?? $template->getContent()))
                ->setEnabled((bool) ($entry['enabled'] ?? $template->isEnabled()))
                ->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($template);
        }

        $this->entityManager->flush();
    }

    public function renderContent(string $content, array $variables = []): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }

        return strtr($content, $replace);
    }

    public function renderByCode(string $code, array $variables = []): ?string
    {
        $this->ensureDefaults();
        $template = $this->templateRepository->findOneBy(['code' => $code]);
        if (!$template || !$template->isEnabled()) {
            return null;
        }

        return $this->renderContent($template->getContent(), $variables);
    }
}
