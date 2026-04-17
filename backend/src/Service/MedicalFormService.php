<?php

namespace App\Service;

use App\Entity\FicheMedicale;
use App\Entity\Formulaire;
use App\Entity\FormulaireChamp;
use App\Entity\FormulaireOnglet;
use App\Entity\FormulaireSection;
use App\Repository\FormulaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MedicalFormService
{
    public const NATIVE_FORM_CODE = 'fiche-medicale-native-v1';

    public function __construct(
        private EntityManagerInterface $em,
        private FormulaireRepository $formulaireRepository,
    ) {
    }

    public function ensureNativeForm(): Formulaire
    {
        $existing = $this->formulaireRepository->findOneByCode(self::NATIVE_FORM_CODE);
        if ($existing instanceof Formulaire) {
            return $existing;
        }

        $definition = $this->getNativeDefinition();
        $formulaire = (new Formulaire())
            ->setCode($definition['code'])
            ->setNom($definition['nom'])
            ->setDescription($definition['description'])
            ->setIsNatif(true)
            ->setVersion((int) ($definition['version'] ?? 1))
            ->setActif(true);

        foreach ($definition['onglets'] as $ongletIndex => $ongletData) {
            $onglet = (new FormulaireOnglet())
                ->setCode($ongletData['code'])
                ->setNom($ongletData['nom'])
                ->setOrdre((int) ($ongletData['ordre'] ?? $ongletIndex + 1))
                ->setActif((bool) ($ongletData['actif'] ?? true));

            foreach ($ongletData['sections'] ?? [] as $sectionIndex => $sectionData) {
                $section = (new FormulaireSection())
                    ->setCode($sectionData['code'])
                    ->setNom($sectionData['nom'])
                    ->setOrdre((int) ($sectionData['ordre'] ?? $sectionIndex + 1))
                    ->setActif((bool) ($sectionData['actif'] ?? true));

                foreach ($sectionData['champs'] ?? [] as $champIndex => $champData) {
                    $champ = (new FormulaireChamp())
                        ->setCode($champData['code'])
                        ->setNom($champData['nom'])
                        ->setType($champData['type'])
                        ->setConfig($champData['config'] ?? [])
                        ->setOrdre((int) ($champData['ordre'] ?? $champIndex + 1))
                        ->setActif((bool) ($champData['actif'] ?? true));
                    $section->addChamp($champ);
                }

                $onglet->addSection($section);
            }

            $formulaire->addOnglet($onglet);
        }

        $this->em->persist($formulaire);
        $this->em->flush();

        return $formulaire;
    }

    /** @return array<int, array<string, mixed>> */
    public function listForms(): array
    {
        return array_map(fn(Formulaire $formulaire): array => $this->serializeFormSummary($formulaire), $this->formulaireRepository->findBy([], ['updatedAt' => 'DESC', 'id' => 'DESC']));
    }

    /** @return array<string, mixed> */
    public function getFormDetail(int $id): array
    {
        return $this->serializeFormDetail($this->getFormulaire($id));
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function createForm(array $payload = []): array
    {
        if (!empty($payload['duplicateFromId'])) {
            return $this->duplicateForm((int) $payload['duplicateFromId'], $payload);
        }

        $formulaire = (new Formulaire())
            ->setCode($this->buildRootCode((string) ($payload['code'] ?? ''), (string) ($payload['nom'] ?? 'Nouveau formulaire')))
            ->setNom(trim((string) ($payload['nom'] ?? 'Nouveau formulaire')) ?: 'Nouveau formulaire')
            ->setDescription($this->normalizeNullableString($payload['description'] ?? null))
            ->setIsNatif(false)
            ->setVersion(1)
            ->setActif((bool) ($payload['actif'] ?? true));

        $structure = $payload['onglets'] ?? null;
        if (is_array($structure) && $structure !== []) {
            $this->replaceFormStructure($formulaire, $structure);
        } else {
            $this->replaceFormStructure($formulaire, [$this->buildDefaultTab()]);
        }

        $this->em->persist($formulaire);
        $formulaire->touch();
        $this->em->flush();

        return $this->serializeFormDetail($formulaire);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function updateForm(int $id, array $payload): array
    {
        $formulaire = $this->getFormulaire($id);
        $formulaire
            ->setNom(trim((string) ($payload['nom'] ?? $formulaire->getNom())) ?: $formulaire->getNom())
            ->setCode($this->buildRootCode((string) ($payload['code'] ?? $formulaire->getCode()), (string) ($payload['nom'] ?? $formulaire->getNom()), $formulaire->getId()))
            ->setDescription($this->normalizeNullableString($payload['description'] ?? $formulaire->getDescription()))
            ->setActif((bool) ($payload['actif'] ?? $formulaire->isActif()));

        if (array_key_exists('onglets', $payload) && is_array($payload['onglets'])) {
            $this->replaceFormStructure($formulaire, $payload['onglets']);
        }

        $formulaire->touch();
        $this->em->flush();

        return $this->serializeFormDetail($formulaire);
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function duplicateForm(int $id, array $payload = []): array
    {
        $source = $this->getFormulaire($id);
        $detail = $this->serializeFormDetail($source);

        $duplicateName = trim((string) ($payload['nom'] ?? ($source->getNom() . ' copie')));
        $duplicate = (new Formulaire())
            ->setCode($this->buildRootCode((string) ($payload['code'] ?? ''), $duplicateName))
            ->setNom($duplicateName ?: ($source->getNom() . ' copie'))
            ->setDescription($this->normalizeNullableString($payload['description'] ?? $source->getDescription()))
            ->setIsNatif(false)
            ->setVersion(1)
            ->setActif((bool) ($payload['actif'] ?? true));

        $this->replaceFormStructure($duplicate, $detail['onglets']);
        $this->em->persist($duplicate);
        $duplicate->touch();
        $this->em->flush();

        return $this->serializeFormDetail($duplicate);
    }

    public function initializeFicheMedicale(FicheMedicale $fiche): FicheMedicale
    {
        if ($fiche->getFormulaireSnapshot() !== null && $fiche->getFormulaire() instanceof Formulaire) {
            return $fiche;
        }

        $formulaire = $fiche->getFormulaire() ?? $this->ensureNativeForm();
        $fiche
            ->setFormulaire($formulaire)
            ->setFormulaireSnapshot($this->buildSnapshot($formulaire));

        return $fiche;
    }

    public function refreshSnapshot(FicheMedicale $fiche, bool $flush = true): void
    {
        $formulaire = $fiche->getFormulaire();
        if (!$formulaire instanceof Formulaire) {
            $formulaire = $this->ensureNativeForm();
            $fiche->setFormulaire($formulaire);
        }

        $fiche->setFormulaireSnapshot($this->buildSnapshot($formulaire));
        if ($flush) {
            $this->em->flush();
        }
    }

    public function buildSnapshot(Formulaire $formulaire): array
    {
        $onglets = $formulaire->getOnglets()->toArray();
        usort($onglets, static fn(FormulaireOnglet $left, FormulaireOnglet $right) => [$left->getOrdre(), $left->getId() ?? 0] <=> [$right->getOrdre(), $right->getId() ?? 0]);

        return [
            'id' => $formulaire->getId(),
            'code' => $formulaire->getCode(),
            'nom' => $formulaire->getNom(),
            'description' => $formulaire->getDescription(),
            'version' => $formulaire->getVersion(),
            'isNatif' => $formulaire->isNatif(),
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'onglets' => array_map(function (FormulaireOnglet $onglet): array {
                $sections = $onglet->getSections()->toArray();
                usort($sections, static fn(FormulaireSection $left, FormulaireSection $right) => [$left->getOrdre(), $left->getId() ?? 0] <=> [$right->getOrdre(), $right->getId() ?? 0]);

                return [
                    'id' => $onglet->getId(),
                    'code' => $onglet->getCode(),
                    'nom' => $onglet->getNom(),
                    'ordre' => $onglet->getOrdre(),
                    'actif' => $onglet->isActif(),
                    'sections' => array_map(function (FormulaireSection $section): array {
                        $champs = $section->getChamps()->toArray();
                        usort($champs, static fn(FormulaireChamp $left, FormulaireChamp $right) => [$left->getOrdre(), $left->getId() ?? 0] <=> [$right->getOrdre(), $right->getId() ?? 0]);

                        return [
                            'id' => $section->getId(),
                            'code' => $section->getCode(),
                            'nom' => $section->getNom(),
                            'ordre' => $section->getOrdre(),
                            'actif' => $section->isActif(),
                            'champs' => array_map(static fn(FormulaireChamp $champ): array => [
                                'id' => $champ->getId(),
                                'code' => $champ->getCode(),
                                'nom' => $champ->getNom(),
                                'type' => $champ->getType(),
                                'ordre' => $champ->getOrdre(),
                                'actif' => $champ->isActif(),
                                'config' => $champ->getConfig(),
                            ], $champs),
                        ];
                    }, $sections),
                ];
            }, $onglets),
        ];
    }

    private function getFormulaire(int $id): Formulaire
    {
        $formulaire = $this->formulaireRepository->find($id);
        if (!$formulaire instanceof Formulaire) {
            throw new NotFoundHttpException(sprintf('Formulaire %d introuvable.', $id));
        }

        return $formulaire;
    }

    /** @param array<int, array<string, mixed>> $onglets */
    private function replaceFormStructure(Formulaire $formulaire, array $onglets): void
    {
        foreach ($formulaire->getOnglets()->toArray() as $existingOnglet) {
            $formulaire->removeOnglet($existingOnglet);
            $this->em->remove($existingOnglet);
        }

        foreach (array_values($onglets) as $ongletIndex => $ongletData) {
            $onglet = (new FormulaireOnglet())
                ->setCode($this->buildNestedCode((string) ($ongletData['code'] ?? ''), (string) ($ongletData['nom'] ?? ('Onglet ' . ($ongletIndex + 1))), 'onglet', $this->collectSiblingCodes($onglets, $ongletIndex)))
                ->setNom(trim((string) ($ongletData['nom'] ?? ('Onglet ' . ($ongletIndex + 1)))) ?: ('Onglet ' . ($ongletIndex + 1)))
                ->setOrdre((int) ($ongletData['ordre'] ?? $ongletIndex + 1))
                ->setActif((bool) ($ongletData['actif'] ?? true));

            $sections = is_array($ongletData['sections'] ?? null) ? array_values($ongletData['sections']) : [];
            if ($sections === []) {
                $sections = [$this->buildDefaultSection()];
            }

            foreach ($sections as $sectionIndex => $sectionData) {
                $section = (new FormulaireSection())
                    ->setCode($this->buildNestedCode((string) ($sectionData['code'] ?? ''), (string) ($sectionData['nom'] ?? ('Section ' . ($sectionIndex + 1))), 'section', $this->collectSiblingCodes($sections, $sectionIndex)))
                    ->setNom(trim((string) ($sectionData['nom'] ?? ('Section ' . ($sectionIndex + 1)))) ?: ('Section ' . ($sectionIndex + 1)))
                    ->setOrdre((int) ($sectionData['ordre'] ?? $sectionIndex + 1))
                    ->setActif((bool) ($sectionData['actif'] ?? true));

                $champs = is_array($sectionData['champs'] ?? null) ? array_values($sectionData['champs']) : [];
                if ($champs === []) {
                    $champs = [$this->buildDefaultField()];
                }

                foreach ($champs as $champIndex => $champData) {
                    $champ = (new FormulaireChamp())
                        ->setCode($this->buildNestedCode((string) ($champData['code'] ?? ''), (string) ($champData['nom'] ?? ('Champ ' . ($champIndex + 1))), 'champ', $this->collectSiblingCodes($champs, $champIndex)))
                        ->setNom(trim((string) ($champData['nom'] ?? ('Champ ' . ($champIndex + 1)))) ?: ('Champ ' . ($champIndex + 1)))
                        ->setType(trim((string) ($champData['type'] ?? 'text')) ?: 'text')
                        ->setConfig(is_array($champData['config'] ?? null) ? $champData['config'] : [])
                        ->setOrdre((int) ($champData['ordre'] ?? $champIndex + 1))
                        ->setActif((bool) ($champData['actif'] ?? true));
                    $section->addChamp($champ);
                }

                $onglet->addSection($section);
            }

            $formulaire->addOnglet($onglet);
        }
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return string[]
     */
    private function collectSiblingCodes(array $items, int $currentIndex): array
    {
        $codes = [];
        foreach ($items as $index => $item) {
            if ($index === $currentIndex) {
                continue;
            }

            if (!empty($item['code']) && is_string($item['code'])) {
                $codes[] = $item['code'];
            }
        }

        return $codes;
    }

    private function buildRootCode(string $candidate, string $fallback, ?int $ignoreId = null): string
    {
        $base = $this->slugify($candidate !== '' ? $candidate : $fallback, 'formulaire');
        $code = $base;
        $suffix = 1;
        while (($existing = $this->formulaireRepository->findOneByCode($code)) instanceof Formulaire && $existing->getId() !== $ignoreId) {
            $code = $base . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }

    /** @param string[] $existingCodes */
    private function buildNestedCode(string $candidate, string $fallback, string $prefix, array $existingCodes): string
    {
        $base = $this->slugify($candidate !== '' ? $candidate : $fallback, $prefix);
        $code = $base;
        $suffix = 1;
        while (in_array($code, $existingCodes, true)) {
            $code = $base . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }

    private function slugify(string $value, string $fallback): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $normalized));
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /** @return array<string, mixed> */
    private function buildDefaultTab(): array
    {
        return [
            'nom' => 'Nouvel onglet',
            'code' => 'nouvel-onglet',
            'ordre' => 1,
            'actif' => true,
            'sections' => [$this->buildDefaultSection()],
        ];
    }

    /** @return array<string, mixed> */
    private function buildDefaultSection(): array
    {
        return [
            'nom' => 'Nouvelle section',
            'code' => 'nouvelle-section',
            'ordre' => 1,
            'actif' => true,
            'champs' => [$this->buildDefaultField()],
        ];
    }

    /** @return array<string, mixed> */
    private function buildDefaultField(): array
    {
        return [
            'nom' => 'Nouveau champ',
            'code' => 'nouveau-champ',
            'type' => 'text',
            'ordre' => 1,
            'actif' => true,
            'config' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function serializeFormSummary(Formulaire $formulaire): array
    {
        $tabCount = 0;
        $sectionCount = 0;
        $fieldCount = 0;
        foreach ($formulaire->getOnglets() as $onglet) {
            $tabCount++;
            foreach ($onglet->getSections() as $section) {
                $sectionCount++;
                $fieldCount += $section->getChamps()->count();
            }
        }

        return [
            'id' => $formulaire->getId(),
            'code' => $formulaire->getCode(),
            'nom' => $formulaire->getNom(),
            'description' => $formulaire->getDescription(),
            'version' => $formulaire->getVersion(),
            'isNatif' => $formulaire->isNatif(),
            'actif' => $formulaire->isActif(),
            'createdAt' => $formulaire->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $formulaire->getUpdatedAt()->format(DATE_ATOM),
            'stats' => [
                'onglets' => $tabCount,
                'sections' => $sectionCount,
                'champs' => $fieldCount,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function serializeFormDetail(Formulaire $formulaire): array
    {
        return [
            ...$this->serializeFormSummary($formulaire),
            'onglets' => array_map(static fn(FormulaireOnglet $onglet): array => [
                'id' => $onglet->getId(),
                'code' => $onglet->getCode(),
                'nom' => $onglet->getNom(),
                'ordre' => $onglet->getOrdre(),
                'actif' => $onglet->isActif(),
                'sections' => array_map(static fn(FormulaireSection $section): array => [
                    'id' => $section->getId(),
                    'code' => $section->getCode(),
                    'nom' => $section->getNom(),
                    'ordre' => $section->getOrdre(),
                    'actif' => $section->isActif(),
                    'champs' => array_map(static fn(FormulaireChamp $champ): array => [
                        'id' => $champ->getId(),
                        'code' => $champ->getCode(),
                        'nom' => $champ->getNom(),
                        'type' => $champ->getType(),
                        'config' => $champ->getConfig(),
                        'ordre' => $champ->getOrdre(),
                        'actif' => $champ->isActif(),
                    ], $section->getChamps()->toArray()),
                ], $onglet->getSections()->toArray()),
            ], $formulaire->getOnglets()->toArray()),
        ];
    }

    public function getNativeDefinition(): array
    {
        return [
            'code' => self::NATIVE_FORM_CODE,
            'nom' => 'Fiche medicale native',
            'description' => 'Formulaire systeme initial derive de la fiche medicale actuelle.',
            'version' => 1,
            'onglets' => [
                [
                    'code' => 'entretien',
                    'nom' => 'Entretien verbal',
                    'ordre' => 1,
                    'sections' => [
                        [
                            'code' => 'consultation',
                            'nom' => 'Consultation',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'entretien_motif_consultation', 'nom' => 'Motif de consultation', 'type' => 'textarea', 'ordre' => 1],
                                ['code' => 'entretien_anamnese', 'nom' => 'Anamnese', 'type' => 'textarea', 'ordre' => 2],
                                ['code' => 'entretien_etat_gynecologique', 'nom' => 'Etat gynecologique', 'type' => 'object', 'ordre' => 3, 'config' => ['schema' => ['allaitement' => 'boolean', 'grossesseEnCours' => 'boolean', 'menstrues' => 'boolean']]],
                            ],
                        ],
                        [
                            'code' => 'antecedents_medicaux',
                            'nom' => 'Antecedents medicaux',
                            'ordre' => 2,
                            'champs' => [
                                ['code' => 'entretien_medicaments', 'nom' => 'Medicaments en cours', 'type' => 'collection_object', 'ordre' => 1],
                                ['code' => 'entretien_affections', 'nom' => 'Affections', 'type' => 'collection_object', 'ordre' => 2],
                                ['code' => 'entretien_questions', 'nom' => 'Questions', 'type' => 'collection_object', 'ordre' => 3],
                                ['code' => 'entretien_habitudes', 'nom' => 'Habitudes de vie', 'type' => 'collection_object', 'ordre' => 4],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'examens',
                    'nom' => 'Examens',
                    'ordre' => 2,
                    'sections' => [
                        [
                            'code' => 'exobuccal',
                            'nom' => 'Examen exobuccal',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'examens_exobuccal_inspection', 'nom' => 'Inspection exobuccale', 'type' => 'object', 'ordre' => 1],
                                ['code' => 'examens_exobuccal_palpation', 'nom' => 'Palpation exobuccale', 'type' => 'object', 'ordre' => 2],
                                ['code' => 'examens_chaines_ganglionnaires', 'nom' => 'Chaines ganglionnaires', 'type' => 'object', 'ordre' => 3],
                            ],
                        ],
                        [
                            'code' => 'endobuccal',
                            'nom' => 'Examen endobuccal',
                            'ordre' => 2,
                            'champs' => [
                                ['code' => 'examens_endobuccal_bouche_fermee', 'nom' => 'Bouche fermee', 'type' => 'object', 'ordre' => 1],
                                ['code' => 'examens_endobuccal_bouche_ouverte', 'nom' => 'Bouche ouverte', 'type' => 'object', 'ordre' => 2],
                                ['code' => 'examens_tissus_mous', 'nom' => 'Table tissus mous', 'type' => 'object', 'ordre' => 3],
                                ['code' => 'examens_tissus_durs', 'nom' => 'Table tissus durs', 'type' => 'object', 'ordre' => 4],
                                ['code' => 'examens_canaux_excreteurs', 'nom' => 'Canaux excreteurs', 'type' => 'textarea', 'ordre' => 5],
                            ],
                        ],
                        [
                            'code' => 'laboratoire',
                            'nom' => 'Examens de laboratoire',
                            'ordre' => 3,
                            'champs' => [
                                ['code' => 'examens_bacteriologiques', 'nom' => 'Examens bacteriologiques', 'type' => 'object', 'ordre' => 1],
                                ['code' => 'examens_serologiques', 'nom' => 'Examens serologiques', 'type' => 'object', 'ordre' => 2],
                                ['code' => 'examens_histologiques', 'nom' => 'Examens histologiques', 'type' => 'object', 'ordre' => 3],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'bilans',
                    'nom' => 'Bilans',
                    'ordre' => 3,
                    'sections' => [
                        [
                            'code' => 'bilan_dentaire',
                            'nom' => 'Bilan dentaire',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'bilans_bilan_dentaire', 'nom' => 'Bilan dentaire', 'type' => 'object', 'ordre' => 1],
                            ],
                        ],
                        [
                            'code' => 'bilan_radiographique',
                            'nom' => 'Bilan radiographique',
                            'ordre' => 2,
                            'champs' => [
                                ['code' => 'bilans_bilan_radiographique', 'nom' => 'Bilan radiographique', 'type' => 'object', 'ordre' => 1],
                            ],
                        ],
                        [
                            'code' => 'bilan_sanguin',
                            'nom' => 'Bilan sanguin',
                            'ordre' => 3,
                            'champs' => [
                                ['code' => 'bilans_bilan_sanguin', 'nom' => 'Bilan sanguin', 'type' => 'object', 'ordre' => 1],
                                ['code' => 'bilans_diagnostic_positif', 'nom' => 'Diagnostic positif', 'type' => 'textarea', 'ordre' => 2],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'documents',
                    'nom' => 'Images et documents',
                    'ordre' => 4,
                    'sections' => [
                        [
                            'code' => 'pieces_jointes',
                            'nom' => 'Pieces jointes',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'documents_collection', 'nom' => 'Documents', 'type' => 'file_collection', 'ordre' => 1],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'plan_traitement',
                    'nom' => 'Plan de traitement',
                    'ordre' => 5,
                    'sections' => [
                        [
                            'code' => 'plans',
                            'nom' => 'Plans',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'plan_traitement_collection', 'nom' => 'Plans de traitement', 'type' => 'collection_object', 'ordre' => 1],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'devis',
                    'nom' => 'Devis',
                    'ordre' => 6,
                    'sections' => [
                        [
                            'code' => 'devis_patient',
                            'nom' => 'Devis patient',
                            'ordre' => 1,
                            'champs' => [
                                ['code' => 'devis_0', 'nom' => 'Devis patient', 'type' => 'object', 'ordre' => 1],
                                ['code' => 'devis_1', 'nom' => 'Facture', 'type' => 'object', 'ordre' => 2],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}