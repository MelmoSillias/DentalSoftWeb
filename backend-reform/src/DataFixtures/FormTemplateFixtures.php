<?php

namespace App\DataFixtures;

use App\ClinicalRecord\Entity\FormTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FormTemplateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $templates = [
            [
                'key' => 'fiche_observation_v1',
                'version' => 1,
                'structure' => [
                    'title' => 'Fiche Observation v1',
                    'required' => ['entretien', 'examens'],
                    'sections' => [
                        'entretien' => ['type' => 'object'],
                        'examens' => ['type' => 'object'],
                        'traitementsDocuments' => ['type' => 'object'],
                        'devis' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'key' => 'fiche_medicale_v2',
                'version' => 2,
                'structure' => [
                    'title' => 'Fiche Medicale v2',
                    'required' => ['entretien', 'examens', 'bilans'],
                    'sections' => [
                        'entretien' => ['type' => 'object'],
                        'examens' => ['type' => 'object'],
                        'bilans' => ['type' => 'object'],
                        'planTraitement' => ['type' => 'array'],
                        'documents' => ['type' => 'array'],
                        'devis' => ['type' => 'array'],
                    ],
                ],
            ],
        ];

        foreach ($templates as $payload) {
            $existing = $manager->getRepository(FormTemplate::class)->findOneBy([
                'key' => $payload['key'],
                'version' => $payload['version'],
            ]);

            if ($existing instanceof FormTemplate) {
                $existing->setStructure($payload['structure']);
                $manager->persist($existing);
                continue;
            }

            $template = new FormTemplate();
            $template->setKey($payload['key']);
            $template->setVersion($payload['version']);
            $template->setStructure($payload['structure']);
            $manager->persist($template);
        }

        $manager->flush();
    }
}
