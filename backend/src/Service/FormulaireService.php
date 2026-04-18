<?php

namespace App\Service;

use App\Entity\Champ;
use App\Entity\FicheMedicale;
use App\Entity\Formulaire;
use App\Entity\Onglet;
use App\Entity\Section;
use App\Repository\FormulaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FormulaireService
{
    public const DEFAULT_MEDICAL_FORM_CODE = 'fiche-medicale-standard';

    private const DEFAULT_FORM_CONFIGURATION = [
        'kind' => 'medical-record',
        'systemSections' => ['infos', 'seances', 'consult'],
        'transitionMode' => 'double-read-double-write',
    ];

    private const DEFAULT_FORM_TABS = [
        [
            'code' => 'evaluation-clinique',
            'title' => 'Evaluation clinique',
            'sortOrder' => 10,
            'configuration' => ['layout' => 'standard'],
            'sections' => [
                [
                    'code' => 'entretien',
                    'title' => 'Entretien verbal',
                    'type' => 'component',
                    'componentKey' => 'entretien-verbal',
                    'sortOrder' => 10,
                    'fields' => [
                        ['code' => 'entretien__motif_consultation', 'label' => 'Motif de consultation', 'fieldType' => 'text', 'sortOrder' => 10],
                        ['code' => 'entretien__anamnese', 'label' => 'Anamnese', 'fieldType' => 'textarea', 'sortOrder' => 20],
                        ['code' => 'entretien__etat_gynecologique', 'label' => 'Etat gynecologique', 'fieldType' => 'json', 'sortOrder' => 30],
                        ['code' => 'entretien__medicaments', 'label' => 'Medicaments', 'fieldType' => 'json', 'sortOrder' => 40],
                        ['code' => 'entretien__affections', 'label' => 'Affections', 'fieldType' => 'json', 'sortOrder' => 50],
                        ['code' => 'entretien__questions', 'label' => 'Questions', 'fieldType' => 'json', 'sortOrder' => 60],
                        ['code' => 'entretien__habitudes', 'label' => 'Habitudes', 'fieldType' => 'json', 'sortOrder' => 70],
                    ],
                ],
                [
                    'code' => 'examens',
                    'title' => 'Examens',
                    'type' => 'component',
                    'componentKey' => 'examens-fiche',
                    'sortOrder' => 20,
                    'fields' => [
                        ['code' => 'examens__exobuccal_inspection', 'label' => 'Exobuccal inspection', 'fieldType' => 'json', 'sortOrder' => 10],
                        ['code' => 'examens__exobuccal_palpation', 'label' => 'Exobuccal palpation', 'fieldType' => 'json', 'sortOrder' => 20],
                        ['code' => 'examens__chaines_ganglionnaires', 'label' => 'Chaines ganglionnaires', 'fieldType' => 'json', 'sortOrder' => 30],
                        ['code' => 'examens__endobuccal_bouche_fermee', 'label' => 'Endobuccal bouche fermee', 'fieldType' => 'json', 'sortOrder' => 40],
                        ['code' => 'examens__endobuccal_bouche_ouverte', 'label' => 'Endobuccal bouche ouverte', 'fieldType' => 'json', 'sortOrder' => 50],
                        ['code' => 'examens__tissus_mous_table', 'label' => 'Tissus mous', 'fieldType' => 'json', 'sortOrder' => 60],
                        ['code' => 'examens__tissus_durs_table', 'label' => 'Tissus durs', 'fieldType' => 'json', 'sortOrder' => 70],
                        ['code' => 'examens__examen_canaux_excreteurs', 'label' => 'Canaux excreteurs', 'fieldType' => 'textarea', 'sortOrder' => 80],
                        ['code' => 'examens__examens_bacteriologiques', 'label' => 'Examens bacteriologiques', 'fieldType' => 'json', 'sortOrder' => 90],
                        ['code' => 'examens__examens_serologiques', 'label' => 'Examens serologiques', 'fieldType' => 'json', 'sortOrder' => 100],
                        ['code' => 'examens__examens_histologiques', 'label' => 'Examens histologiques', 'fieldType' => 'json', 'sortOrder' => 110],
                    ],
                ],
                [
                    'code' => 'bilans',
                    'title' => 'Bilans',
                    'type' => 'component',
                    'componentKey' => 'fiche-bilans',
                    'sortOrder' => 30,
                    'fields' => [
                        ['code' => 'bilans__bilan_dentaire', 'label' => 'Bilan dentaire', 'fieldType' => 'json', 'sortOrder' => 10],
                        ['code' => 'bilans__bilan_radiographique', 'label' => 'Bilan radiographique', 'fieldType' => 'json', 'sortOrder' => 20],
                        ['code' => 'bilans__bilan_sanguin', 'label' => 'Bilan sanguin', 'fieldType' => 'json', 'sortOrder' => 30],
                        ['code' => 'bilans__diagnostic_positif', 'label' => 'Diagnostic positif', 'fieldType' => 'textarea', 'sortOrder' => 40],
                    ],
                ],
            ],
        ],
        [
            'code' => 'prise-en-charge',
            'title' => 'Prise en charge',
            'sortOrder' => 20,
            'configuration' => ['layout' => 'standard'],
            'sections' => [
                [
                    'code' => 'documents',
                    'title' => 'Images et docs',
                    'type' => 'component',
                    'componentKey' => 'fiche-documents',
                    'sortOrder' => 10,
                    'fields' => [
                        ['code' => 'documents__items', 'label' => 'Documents', 'fieldType' => 'json', 'sortOrder' => 10],
                    ],
                ],
                [
                    'code' => 'plan-traitement',
                    'title' => 'Plan de traitement',
                    'type' => 'component',
                    'componentKey' => 'plan-traitement',
                    'sortOrder' => 20,
                    'fields' => [
                        ['code' => 'plan_traitement__items', 'label' => 'Plan de traitement', 'fieldType' => 'json', 'sortOrder' => 10],
                    ],
                ],
                [
                    'code' => 'devis',
                    'title' => 'Devis',
                    'type' => 'component',
                    'componentKey' => 'devis',
                    'sortOrder' => 30,
                    'fields' => [
                        ['code' => 'devis__items', 'label' => 'Devis', 'fieldType' => 'json', 'sortOrder' => 10],
                    ],
                ],
            ],
        ],
    ];

    public function __construct(
        private FormulaireRepository $formulaireRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function ensureDefaultPublishedForm(): Formulaire
    {
        $existing = $this->formulaireRepository->findPublishedByCode(self::DEFAULT_MEDICAL_FORM_CODE);
        if ($existing) {
            return $existing;
        }

        $formulaire = $this->buildFormulaireFromDefinition(
            code: self::DEFAULT_MEDICAL_FORM_CODE,
            label: 'Fiche medicale standard',
            version: 1,
            status: Formulaire::STATUS_PUBLISHED,
            source: null,
        );
        $formulaire->setDescription('Version standard migree depuis la fiche medicale existante.');
        $formulaire->setPublishedAt(new \DateTimeImmutable());

        $this->em->persist($formulaire);
        $this->em->flush();

        return $formulaire;
    }

    public function ensureFicheHasPublishedForm(FicheMedicale $fiche): Formulaire
    {
        $formulaire = $fiche->getFormulaireVersion();
        if ($formulaire instanceof Formulaire && $formulaire->getStatus() === Formulaire::STATUS_PUBLISHED) {
            return $formulaire;
        }

        $formulaire = $this->ensureDefaultPublishedForm();
        $fiche->setFormulaireVersion($formulaire);

        return $formulaire;
    }

    public function findFormulaireOrFail(int $id): Formulaire
    {
        $formulaire = $this->formulaireRepository->find($id);
        if (!$formulaire) {
            throw new NotFoundHttpException(sprintf('Formulaire %d introuvable.', $id));
        }

        return $formulaire;
    }

    /** @return array<int, array<string, mixed>> */
    public function listFormulaires(): array
    {
        $items = $this->formulaireRepository->createQueryBuilder('f')
            ->orderBy('f.updatedAt', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Formulaire $formulaire): array => $this->serializeFormulaire($formulaire), $items);
    }

    public function createFormulaireFromPayload(array $payload): Formulaire
    {
        $code = $this->resolveCode((string) ($payload['code'] ?? ''));
        $label = trim((string) ($payload['label'] ?? 'Formulaire'));
        if ($label === '') {
            throw new BadRequestHttpException('Le label du formulaire est requis.');
        }

        $latest = $this->formulaireRepository->findLatestVersion($code);
        $version = $latest ? ($latest->getVersion() + 1) : 1;

        $formulaire = (new Formulaire())
            ->setCode($code)
            ->setLabel($label)
            ->setVersion($version)
            ->setStatus((string) ($payload['status'] ?? Formulaire::STATUS_DRAFT))
            ->setDescription($this->normalizeNullableString($payload['description'] ?? null))
            ->setConfiguration($this->normalizeArray($payload['configuration'] ?? self::DEFAULT_FORM_CONFIGURATION));

        $this->applyStructureFromPayload($formulaire, $payload);

        $this->em->persist($formulaire);
        $this->em->flush();

        return $formulaire;
    }

    public function updateFormulaireFromPayload(Formulaire $formulaire, array $payload): Formulaire
    {
        $label = trim((string) ($payload['label'] ?? $formulaire->getLabel()));
        if ($label === '') {
            throw new BadRequestHttpException('Le label du formulaire est requis.');
        }

        $targetCode = $this->resolveCode((string) ($payload['code'] ?? $formulaire->getCode()));
        if ($targetCode !== $formulaire->getCode()) {
            $latest = $this->formulaireRepository->findLatestVersion($targetCode);
            if ($latest) {
                throw new BadRequestHttpException(sprintf('Le code "%s" existe deja. Dupliquez plutot une nouvelle version.', $targetCode));
            }
            $formulaire->setCode($targetCode);
        }

        $formulaire
            ->setLabel($label)
            ->setDescription($this->normalizeNullableString($payload['description'] ?? $formulaire->getDescription()))
            ->setConfiguration($this->normalizeArray($payload['configuration'] ?? $formulaire->getConfiguration()));

        if (array_key_exists('status', $payload)) {
            $formulaire->setStatus((string) $payload['status']);
        }

        $this->clearStructure($formulaire);
        $this->applyStructureFromPayload($formulaire, $payload);

        $this->em->persist($formulaire);
        $this->em->flush();

        return $formulaire;
    }

    public function deleteFormulaire(Formulaire $formulaire): void
    {
        if ($formulaire->getFichesMedicales()->count() > 0) {
            $formulaire->setStatus(Formulaire::STATUS_ARCHIVED);
            $this->em->persist($formulaire);
            $this->em->flush();

            return;
        }

        $this->em->remove($formulaire);
        $this->em->flush();
    }

    public function duplicateFormulaire(Formulaire $source, ?string $label = null): Formulaire
    {
        $nextVersion = ($this->formulaireRepository->findLatestVersion($source->getCode())?->getVersion() ?? $source->getVersion()) + 1;
        $draft = $this->cloneFormulaire($source, $nextVersion, $label ?: $source->getLabel());
        $draft->setDescription($source->getDescription());
        $draft->setConfiguration($source->getConfiguration());

        $this->em->persist($draft);
        $this->em->flush();

        return $draft;
    }

    public function publishFormulaire(Formulaire $formulaire): Formulaire
    {
        $published = $this->formulaireRepository->findPublishedByCode($formulaire->getCode());
        if ($published && $published->getId() !== $formulaire->getId()) {
            $published->setStatus(Formulaire::STATUS_ARCHIVED);
            $this->em->persist($published);
        }

        $formulaire->setStatus(Formulaire::STATUS_PUBLISHED);
        $formulaire->setPublishedAt(new \DateTimeImmutable());
        $this->em->persist($formulaire);
        $this->em->flush();

        return $formulaire;
    }

    public function findChampByCode(Formulaire $formulaire, string $champCode): ?Champ
    {
        foreach ($formulaire->getOnglets() as $onglet) {
            foreach ($onglet->getSections() as $section) {
                foreach ($section->getChamps() as $champ) {
                    if ($champ->getCode() === $champCode) {
                        return $champ;
                    }
                }
            }
        }

        return null;
    }

    public function findSectionByCode(Formulaire $formulaire, string $sectionCode): ?Section
    {
        foreach ($formulaire->getOnglets() as $onglet) {
            foreach ($onglet->getSections() as $section) {
                if ($section->getCode() === $sectionCode) {
                    return $section;
                }
            }
        }

        return null;
    }

    public function serializeFormulaire(Formulaire $formulaire): array
    {
        return [
            'id' => $formulaire->getId(),
            'code' => $formulaire->getCode(),
            'label' => $formulaire->getLabel(),
            'version' => $formulaire->getVersion(),
            'status' => $formulaire->getStatus(),
            'description' => $formulaire->getDescription(),
            'configuration' => $formulaire->getConfiguration(),
            'publishedAt' => $formulaire->getPublishedAt()?->format('Y-m-d H:i:s'),
            'onglets' => array_map(static function (Onglet $onglet): array {
                return [
                    'id' => $onglet->getId(),
                    'code' => $onglet->getCode(),
                    'title' => $onglet->getTitle(),
                    'sortOrder' => $onglet->getSortOrder(),
                    'configuration' => $onglet->getConfiguration(),
                    'sections' => array_map(static function (Section $section): array {
                        return [
                            'id' => $section->getId(),
                            'code' => $section->getCode(),
                            'title' => $section->getTitle(),
                            'type' => $section->getType(),
                            'componentKey' => $section->getComponentKey(),
                            'sortOrder' => $section->getSortOrder(),
                            'configuration' => $section->getConfiguration(),
                            'conditions' => $section->getConditions(),
                            'fields' => array_map(static function (Champ $champ): array {
                                return [
                                    'id' => $champ->getId(),
                                    'code' => $champ->getCode(),
                                    'label' => $champ->getLabel(),
                                    'fieldType' => $champ->getFieldType(),
                                    'rendererKey' => $champ->getRendererKey(),
                                    'sortOrder' => $champ->getSortOrder(),
                                    'isRequired' => $champ->isRequired(),
                                    'isRepeated' => $champ->isRepeated(),
                                    'defaultValue' => $champ->getDefaultValue(),
                                    'options' => $champ->getOptions(),
                                    'validationRules' => $champ->getValidationRules(),
                                    'conditions' => $champ->getConditions(),
                                ];
                            }, $section->getChamps()->toArray()),
                        ];
                    }, $onglet->getSections()->toArray()),
                ];
            }, $formulaire->getOnglets()->toArray()),
        ];
    }

    private function buildFormulaireFromDefinition(
        string $code,
        string $label,
        int $version,
        string $status,
        ?string $source = null,
        ?Formulaire $sourceFormulaire = null,
    ): Formulaire {
        $formulaire = (new Formulaire())
            ->setCode($code)
            ->setLabel($label)
            ->setVersion($version)
            ->setStatus($status)
            ->setSourceFormulaire($sourceFormulaire)
            ->setConfiguration([
                ...self::DEFAULT_FORM_CONFIGURATION,
                'source' => $source ?: 'default-seed',
            ]);

        foreach (self::DEFAULT_FORM_TABS as $tabData) {
            $onglet = (new Onglet())
                ->setCode($tabData['code'])
                ->setTitle($tabData['title'])
                ->setSortOrder($tabData['sortOrder'])
                ->setConfiguration($tabData['configuration'] ?? []);

            foreach ($tabData['sections'] as $sectionData) {
                $section = (new Section())
                    ->setCode($sectionData['code'])
                    ->setTitle($sectionData['title'])
                    ->setType($sectionData['type'] ?? 'component')
                    ->setComponentKey($sectionData['componentKey'] ?? null)
                    ->setSortOrder($sectionData['sortOrder'] ?? 0)
                    ->setConfiguration($sectionData['configuration'] ?? [])
                    ->setConditions($sectionData['conditions'] ?? []);

                foreach ($sectionData['fields'] ?? [] as $fieldData) {
                    $champ = (new Champ())
                        ->setCode($fieldData['code'])
                        ->setLabel($fieldData['label'])
                        ->setFieldType($fieldData['fieldType'] ?? 'json')
                        ->setRendererKey($fieldData['rendererKey'] ?? null)
                        ->setSortOrder($fieldData['sortOrder'] ?? 0)
                        ->setIsRequired((bool) ($fieldData['isRequired'] ?? false))
                        ->setIsRepeated((bool) ($fieldData['isRepeated'] ?? false))
                        ->setDefaultValue($fieldData['defaultValue'] ?? null)
                        ->setOptions($fieldData['options'] ?? [])
                        ->setValidationRules($fieldData['validationRules'] ?? [])
                        ->setConditions($fieldData['conditions'] ?? []);
                    $section->addChamp($champ);
                }

                $onglet->addSection($section);
            }

            $formulaire->addOnglet($onglet);
        }

        return $formulaire;
    }

    private function cloneFormulaire(Formulaire $source, int $version, string $label): Formulaire
    {
        $draft = (new Formulaire())
            ->setCode($source->getCode())
            ->setLabel($label)
            ->setVersion($version)
            ->setStatus(Formulaire::STATUS_DRAFT)
            ->setSourceFormulaire($source)
            ->setConfiguration($source->getConfiguration())
            ->setDescription($source->getDescription());

        foreach ($source->getOnglets() as $sourceOnglet) {
            $onglet = (new Onglet())
                ->setCode($sourceOnglet->getCode())
                ->setTitle($sourceOnglet->getTitle())
                ->setSortOrder($sourceOnglet->getSortOrder())
                ->setConfiguration($sourceOnglet->getConfiguration());

            foreach ($sourceOnglet->getSections() as $sourceSection) {
                $section = (new Section())
                    ->setCode($sourceSection->getCode())
                    ->setTitle($sourceSection->getTitle())
                    ->setType($sourceSection->getType())
                    ->setComponentKey($sourceSection->getComponentKey())
                    ->setSortOrder($sourceSection->getSortOrder())
                    ->setConfiguration($sourceSection->getConfiguration())
                    ->setConditions($sourceSection->getConditions());

                foreach ($sourceSection->getChamps() as $sourceChamp) {
                    $champ = (new Champ())
                        ->setCode($sourceChamp->getCode())
                        ->setLabel($sourceChamp->getLabel())
                        ->setFieldType($sourceChamp->getFieldType())
                        ->setRendererKey($sourceChamp->getRendererKey())
                        ->setSortOrder($sourceChamp->getSortOrder())
                        ->setIsRequired($sourceChamp->isRequired())
                        ->setIsRepeated($sourceChamp->isRepeated())
                        ->setDefaultValue($sourceChamp->getDefaultValue())
                        ->setOptions($sourceChamp->getOptions())
                        ->setValidationRules($sourceChamp->getValidationRules())
                        ->setConditions($sourceChamp->getConditions());
                    $section->addChamp($champ);
                }

                $onglet->addSection($section);
            }

            $draft->addOnglet($onglet);
        }

        return $draft;
    }

    private function clearStructure(Formulaire $formulaire): void
    {
        foreach ($formulaire->getOnglets()->toArray() as $onglet) {
            $formulaire->removeOnglet($onglet);
        }
    }

    private function applyStructureFromPayload(Formulaire $formulaire, array $payload): void
    {
        $tabs = $this->normalizeArray($payload['onglets'] ?? []);
        foreach ($tabs as $tabIndex => $tabData) {
            $tabData = is_array($tabData) ? $tabData : [];
            $tabTitle = trim((string) ($tabData['title'] ?? ''));
            if ($tabTitle === '') {
                $tabTitle = sprintf('Onglet %d', $tabIndex + 1);
            }

            $onglet = (new Onglet())
                ->setCode($this->resolveCode((string) ($tabData['code'] ?? $tabTitle)))
                ->setTitle($tabTitle)
                ->setSortOrder((int) ($tabData['sortOrder'] ?? (($tabIndex + 1) * 10)))
                ->setConfiguration($this->normalizeArray($tabData['configuration'] ?? []));

            $sections = $this->normalizeArray($tabData['sections'] ?? []);
            foreach ($sections as $sectionIndex => $sectionData) {
                $sectionData = is_array($sectionData) ? $sectionData : [];
                $sectionTitle = trim((string) ($sectionData['title'] ?? ''));
                if ($sectionTitle === '') {
                    $sectionTitle = sprintf('Section %d', $sectionIndex + 1);
                }

                $section = (new Section())
                    ->setCode($this->resolveCode((string) ($sectionData['code'] ?? $sectionTitle)))
                    ->setTitle($sectionTitle)
                    ->setType((string) ($sectionData['type'] ?? 'component'))
                    ->setComponentKey($this->normalizeNullableString($sectionData['componentKey'] ?? null))
                    ->setSortOrder((int) ($sectionData['sortOrder'] ?? (($sectionIndex + 1) * 10)))
                    ->setConfiguration($this->normalizeArray($sectionData['configuration'] ?? []))
                    ->setConditions($this->normalizeArray($sectionData['conditions'] ?? []));

                $fields = $this->normalizeArray($sectionData['fields'] ?? []);
                foreach ($fields as $fieldIndex => $fieldData) {
                    $fieldData = is_array($fieldData) ? $fieldData : [];
                    $fieldLabel = trim((string) ($fieldData['label'] ?? ''));
                    if ($fieldLabel === '') {
                        $fieldLabel = sprintf('Champ %d', $fieldIndex + 1);
                    }

                    $champ = (new Champ())
                        ->setCode($this->resolveCode((string) ($fieldData['code'] ?? $fieldLabel)))
                        ->setLabel($fieldLabel)
                        ->setFieldType((string) ($fieldData['fieldType'] ?? 'text'))
                        ->setRendererKey($this->normalizeNullableString($fieldData['rendererKey'] ?? null))
                        ->setSortOrder((int) ($fieldData['sortOrder'] ?? (($fieldIndex + 1) * 10)))
                        ->setIsRequired((bool) ($fieldData['isRequired'] ?? false))
                        ->setIsRepeated((bool) ($fieldData['isRepeated'] ?? false))
                        ->setDefaultValue($fieldData['defaultValue'] ?? null)
                        ->setOptions($this->normalizeArray($fieldData['options'] ?? []))
                        ->setValidationRules($this->normalizeArray($fieldData['validationRules'] ?? []))
                        ->setConditions($this->normalizeArray($fieldData['conditions'] ?? []));
                    $section->addChamp($champ);
                }

                $onglet->addSection($section);
            }

            $formulaire->addOnglet($onglet);
        }
    }

    private function resolveCode(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?: '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            return 'form-' . bin2hex(random_bytes(4));
        }

        return mb_substr($normalized, 0, 120);
    }

    private function normalizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }
}