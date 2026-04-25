<?php

namespace App\ClinicalRecord\Service;

use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Entity\FormTemplate;
use App\ClinicalRecord\Repository\FormTemplateRepository;
use App\Settings\Service\GlobalSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FicheMedicaleService
{
    private const TEMPLATE_V1 = 'fiche_observation_v1';
    private const TEMPLATE_V2 = 'fiche_medicale_v2';

    private string $projectDir;

    public function __construct(
        private EntityManagerInterface $em,
        private FormTemplateRepository $formTemplateRepository,
        private GlobalSettingsService $globalSettingsService,
        ParameterBagInterface $params,
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
    }

    private function resolveDefaultFormTemplate(): string
    {
        return $this->globalSettingsService->getDefaultFormTemplate();
    }

    private function getFiche(int $ficheId): FicheMedicale
    {
        $fiche = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if (!$fiche) {
            throw new NotFoundHttpException("FicheMedicale {$ficheId} introuvable");
        }

        return $fiche;
    }

    private function resolveTemplateVersion(string $templateKey): int
    {
        return $templateKey === self::TEMPLATE_V2 ? 2 : 1;
    }

    private function getTemplateByKey(string $key): FormTemplate
    {
        $template = $this->formTemplateRepository->findLatestByKey($key);
        if (!$template) {
            // Keep update endpoint operational even if fixtures were not loaded.
            $template = new FormTemplate();
            $template->setKey($key);
            $template->setVersion($this->resolveTemplateVersion($key));
            $template->setStructure(['required' => []]);
            $this->em->persist($template);
            $this->em->flush();
        }

        return $template;
    }

    private function validateFormDataWithTemplate(FormTemplate $template, array $formData): void
    {
        $structure = $template->getStructure();
        $required = $structure['required'] ?? [];
        if (!is_array($required)) {
            return;
        }

        $missing = [];
        foreach ($required as $field) {
            if (!is_string($field)) {
                continue;
            }

            if (!array_key_exists($field, $formData)) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new \InvalidArgumentException('formData incomplet. Champs obligatoires manquants: ' . implode(', ', $missing));
        }
    }

    private function normalizeDocumentEntry(array $entry): array
    {
        $urls = $entry['urls'] ?? (isset($entry['url']) ? [$entry['url']] : []);
        if (!is_array($urls)) {
            $urls = [];
        }

        return [
            'groupKey' => isset($entry['groupKey']) && is_string($entry['groupKey']) && trim($entry['groupKey']) !== ''
                ? trim($entry['groupKey'])
                : uniqid('doc_', true),
            'type' => isset($entry['type']) && is_string($entry['type']) && trim($entry['type']) !== '' ? trim($entry['type']) : 'Document',
            'libelle' => isset($entry['libelle']) && is_string($entry['libelle']) ? trim($entry['libelle']) : '',
            'urls' => array_values(array_filter(array_map(static fn(mixed $url): ?string => is_string($url) && trim($url) !== '' ? trim($url) : null, $urls))),
        ];
    }

    private function extractDocumentsFromFormData(array $formData): array
    {
        $documents = $formData['documents'] ?? null;
        if (!is_array($documents)) {
            $documents = $formData['traitementsDocuments']['documents'] ?? [];
        }

        if (!is_array($documents)) {
            return [];
        }

        $result = [];
        foreach ($documents as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $result[] = $this->normalizeDocumentEntry($doc);
        }

        return $result;
    }

    private function ensureDocumentsTargetPath(array &$formData, string $templateKey): string
    {
        if (isset($formData['documents']) && is_array($formData['documents'])) {
            $formData['documents'] = $this->extractDocumentsFromFormData($formData);
            return 'documents';
        }

        if (isset($formData['traitementsDocuments']) && is_array($formData['traitementsDocuments'])) {
            $formData['traitementsDocuments']['documents'] = $this->extractDocumentsFromFormData($formData);
            return 'traitementsDocuments.documents';
        }

        if ($templateKey === self::TEMPLATE_V1) {
            $formData['traitementsDocuments'] = $formData['traitementsDocuments'] ?? [];
            if (!is_array($formData['traitementsDocuments'])) {
                $formData['traitementsDocuments'] = [];
            }
            $formData['traitementsDocuments']['documents'] = [];
            return 'traitementsDocuments.documents';
        }

        $formData['documents'] = [];
        return 'documents';
    }

    private function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $index => $docFiles) {
            if ($docFiles instanceof UploadedFile) {
                $normalized[$index] = [$docFiles];
                continue;
            }

            if (!is_array($docFiles)) {
                continue;
            }

            $flat = [];
            array_walk_recursive($docFiles, static function (mixed $item) use (&$flat): void {
                if ($item instanceof UploadedFile) {
                    $flat[] = $item;
                }
            });

            if ($flat !== []) {
                $normalized[$index] = $flat;
            }
        }

        return $normalized;
    }

    private function getUploadDirectory(): string
    {
        $uploadDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'fiche-medicale';
        $fs = new Filesystem();
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0775);
            $fs->chmod($uploadDir, 0775);
        }

        return $uploadDir;
    }

    private function moveUploadedFile(UploadedFile $file, string $uploadDir): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_BASENAME);
        if (!$originalName) {
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
            $originalName = 'document' . ($extension ? '.' . $extension : '');
        }

        $filename = $originalName;
        $namePart = pathinfo($originalName, PATHINFO_FILENAME);
        $extensionPart = pathinfo($originalName, PATHINFO_EXTENSION);

        $counter = 1;
        while (file_exists($uploadDir . DIRECTORY_SEPARATOR . $filename)) {
            $suffix = '_' . $counter;
            $filename = $namePart . $suffix . ($extensionPart ? '.' . $extensionPart : '');
            $counter++;
        }

        $movedFile = $file->move($uploadDir, $filename);

        return 'uploads/documents/fiche-medicale/' . $movedFile->getFilename();
    }

    private function appendUploadedFilesToFormData(array $formData, string $templateKey, array $files): array
    {
        $normalizedFiles = $this->normalizeUploadedFiles($files);
        if ($normalizedFiles === []) {
            return $formData;
        }

        $documentsPath = $this->ensureDocumentsTargetPath($formData, $templateKey);
        $uploadDir = $this->getUploadDirectory();

        if ($documentsPath === 'documents') {
            $documents = &$formData['documents'];
        } else {
            $documents = &$formData['traitementsDocuments']['documents'];
        }

        foreach ($normalizedFiles as $index => $docFiles) {
            if (!isset($documents[$index]) || !is_array($documents[$index])) {
                $documents[$index] = [
                    'groupKey' => uniqid('doc_', true),
                    'type' => 'Document',
                    'libelle' => '',
                    'urls' => [],
                ];
            }

            $documents[$index] = $this->normalizeDocumentEntry($documents[$index]);

            foreach ($docFiles as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $url = $this->moveUploadedFile($file, $uploadDir);
                $documents[$index]['urls'][] = $url;
            }

            $documents[$index]['urls'] = array_values(array_unique($documents[$index]['urls']));
        }

        return $formData;
    }

    private function extractMotif(array $formData): string
    {
        $entretien = $formData['entretien'] ?? null;
        if (!is_array($entretien)) {
            return '';
        }

        $motif = $entretien['motifConsultation'] ?? $entretien['motif'] ?? '';

        return is_string($motif) ? $motif : '';
    }

    private function normalizeTopLevelSections(array $formData): array
    {
        return [
            'entretien' => is_array($formData['entretien'] ?? null) ? $formData['entretien'] : null,
            'examens' => is_array($formData['examens'] ?? null) ? $formData['examens'] : null,
            'bilans' => is_array($formData['bilans'] ?? null) ? $formData['bilans'] : null,
            'planTraitement' => is_array($formData['planTraitement'] ?? null) ? $formData['planTraitement'] : [],
            'documents' => $this->extractDocumentsFromFormData($formData),
            'devis' => $formData['devis'] ?? [],
            'motif' => $this->extractMotif($formData),
        ];
    }

    public function updateFromTemplate(int $ficheId, ?string $templateKey, array $formData, array $files = []): array
    {
        $fiche = $this->getFiche($ficheId);

        $resolvedTemplateKey = $templateKey ?: $fiche->getFormTemplateKey() ?: $this->resolveDefaultFormTemplate();
        $template = $this->getTemplateByKey($resolvedTemplateKey);
        $this->validateFormDataWithTemplate($template, $formData);

        $preparedFormData = $this->appendUploadedFilesToFormData($formData, $resolvedTemplateKey, $files);

        $fiche->setFormTemplateKey($resolvedTemplateKey);
        $fiche->setFormData($preparedFormData);
        $this->em->persist($fiche);
        $this->em->flush();

        return $this->getFicheJson($ficheId);
    }

    public function getFicheJson(int $ficheId): array
    {
        $fiche = $this->getFiche($ficheId);
        $patient = $fiche->getPatient();
        $resolvedTemplateKey = $fiche->getFormTemplateKey() ?: $this->resolveDefaultFormTemplate();
        $template = $this->formTemplateRepository->findLatestByKey($resolvedTemplateKey);

        $formData = $fiche->getFormData() ?? [];
        $sections = $this->normalizeTopLevelSections($formData);

        $consultations = array_values(array_map(static fn($c) => [
            'id' => $c->getId(),
            'type' => $c->getType(),
            'noteSeance' => $c->getNoteSeance(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'medecin' => $c->getMedecin() ? [
                'id' => $c->getMedecin()->getId(),
                'name' => $c->getMedecin()->getFullName(),
            ] : null,
        ], array_filter(
            $fiche->getConsultations()->toArray(),
            static fn($c) => $c->getStatut() === 1
        )));

        return [
            'id' => $fiche->getId(),
            'createdAt' => $fiche->getCreatedAt()?->format('Y-m-d H:i:s'),
            'formTemplateKey' => $resolvedTemplateKey,
            'formTemplate' => $template ? [
                'id' => $template->getId(),
                'key' => $template->getKey(),
                'version' => $template->getVersion(),
                'structure' => $template->getStructure(),
            ] : null,
            'formData' => $formData,
            'patient' => [
                'id' => $patient?->getId(),
                'nom' => $patient?->getNom(),
                'prenom' => $patient?->getPrenom(),
                'sexe' => $patient?->getSexe(),
                'dateNaissance' => $patient?->getDateNaissance()?->format('Y-m-d'),
                'telephone' => $patient?->getTelephone(),
                'email' => $patient?->getEmail(),
                'profession' => $patient?->getProfession(),
                'lieuNaissance' => $patient?->getLieuNaissance(),
                'adresse' => $patient?->getAdresse(),
            ],
            'motif' => $sections['motif'],
            'entretien' => $sections['entretien'],
            'examens' => $sections['examens'],
            'bilans' => $sections['bilans'],
            'planTraitement' => $sections['planTraitement'],
            'documents' => $sections['documents'],
            'devis' => $sections['devis'],
            'consultations' => $consultations,
        ];
    }
}
