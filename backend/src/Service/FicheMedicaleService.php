<?php

namespace App\Service;

use App\Entity\ContenuDevis;
use App\Entity\Devis;
use App\Entity\FicheBilan;
use App\Entity\FicheDocument;
use App\Entity\FicheEntretien;
use App\Entity\FicheEntretienAffection;
use App\Entity\FicheEntretienHabitude;
use App\Entity\FicheEntretienMedicament;
use App\Entity\FicheEntretienQuestion;
use App\Entity\FicheExamen;
use App\Entity\FicheExamenItem;
use App\Entity\FicheExamenLabo;
use App\Entity\FicheMedicale;
use App\Entity\FichePlanTraitement;
use App\Repository\DevisRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FicheMedicaleService
{
    private string $projectDir;

    public function __construct(
        private EntityManagerInterface $em,
        private DevisRepository $devisRepo,
        ParameterBagInterface $params,
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
    }

    private function getFiche(int $ficheId): FicheMedicale
    {
        $fiche = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if (!$fiche) {
            throw new NotFoundHttpException("FicheMedicale {$ficheId} introuvable");
        }
        return $fiche;
    }

    private function toBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        if (in_array($normalized, ['1', 'true', 'oui', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'non', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    private function clearCollection(Collection $collection): void
    {
        foreach ($collection->toArray() as $item) {
            $collection->removeElement($item);
            $this->em->remove($item);
        }
    }

    private function getOrCreateEntretien(FicheMedicale $fiche): FicheEntretien
    {
        $entretien = $fiche->getEntretien();
        if (!$entretien) {
            $entretien = new FicheEntretien();
            $entretien->setFicheMedicale($fiche);
            $fiche->setEntretien($entretien);
            $this->em->persist($entretien);
        }
        return $entretien;
    }

    private function getOrCreateExamen(FicheMedicale $fiche): FicheExamen
    {
        $examen = $fiche->getExamen();
        if (!$examen) {
            $examen = new FicheExamen();
            $examen->setFicheMedicale($fiche);
            $fiche->setExamen($examen);
            $this->em->persist($examen);
        }
        return $examen;
    }

    private function getOrCreateBilan(FicheMedicale $fiche): FicheBilan
    {
        $bilan = $fiche->getBilan();
        if (!$bilan) {
            $bilan = new FicheBilan();
            $bilan->setFicheMedicale($fiche);
            $fiche->setBilan($bilan);
            $this->em->persist($bilan);
        }
        return $bilan;
    }

    public function updateEntretien(int $ficheId, array $data): void
    {
        $fiche = $this->getFiche($ficheId);
        $entretien = $this->getOrCreateEntretien($fiche);

        if (array_key_exists('motifConsultation', $data)) {
            $entretien->setMotifConsultation($data['motifConsultation']);
        }
        if (array_key_exists('anamnese', $data)) {
            $entretien->setAnamnese($data['anamnese']);
        }

        $etatGyneco = $data['etatGynecologique'] ?? $data['etatGenecoloque'] ?? null;
        if (is_array($etatGyneco)) {
            $entretien->setAllaitement($this->toBool($etatGyneco['allaitement'] ?? $etatGyneco['alaitement'] ?? null));
            $entretien->setGrossesseEnCours($this->toBool($etatGyneco['grossesseEnCours'] ?? $etatGyneco['grossesse'] ?? null));
            $entretien->setMenstrues($this->toBool($etatGyneco['menstrues'] ?? null));
        } else {
            if (array_key_exists('allaitement', $data)) {
                $entretien->setAllaitement($this->toBool($data['allaitement']));
            }
            if (array_key_exists('grossesseEnCours', $data)) {
                $entretien->setGrossesseEnCours($this->toBool($data['grossesseEnCours']));
            }
            if (array_key_exists('menstrues', $data)) {
                $entretien->setMenstrues($this->toBool($data['menstrues']));
            }
        }

        if (isset($data['medicaments']) && is_array($data['medicaments'])) {
            $this->clearCollection($entretien->getMedicaments());
            foreach ($data['medicaments'] as $m) {
                $med = new FicheEntretienMedicament();
                $med->setEntretien($entretien);
                $med->setNom($m['nom'] ?? $m['name'] ?? $m['key'] ?? (is_string($m) ? $m : null));
                $med->setEstUtilise($this->toBool($m['estUtilise'] ?? $m['value'] ?? $m['utilise'] ?? null));
                $med->setDetails($m['details'] ?? $m['description'] ?? null);
                $this->em->persist($med);
            }
        }

        if (isset($data['affections']) && is_array($data['affections'])) {
            $this->clearCollection($entretien->getAffections());
            foreach ($data['affections'] as $a) {
                $aff = new FicheEntretienAffection();
                $aff->setEntretien($entretien);
                $aff->setNom($a['nom'] ?? $a['name'] ?? $a['key'] ?? (is_string($a) ? $a : null));
                $aff->setEstPresente($this->toBool($a['estPresente'] ?? $a['value'] ?? $a['present'] ?? null));
                $aff->setDetails($a['details'] ?? $a['description'] ?? null);
                $this->em->persist($aff);
            }
        }

        if (isset($data['questions']) && is_array($data['questions'])) {
            $this->clearCollection($entretien->getQuestions());
            foreach ($data['questions'] as $q) {
                $question = new FicheEntretienQuestion();
                $question->setEntretien($entretien);
                $question->setQuestion($q['question'] ?? $q['key'] ?? (is_string($q) ? $q : null));
                $question->setReponse($this->toBool($q['reponse'] ?? $q['value'] ?? $q['oui'] ?? null));
                $question->setPrecision($q['precision'] ?? $q['details'] ?? null);
                $this->em->persist($question);
            }
        }

        if (isset($data['habitudes']) && is_array($data['habitudes'])) {
            $this->clearCollection($entretien->getHabitudes());
            foreach ($data['habitudes'] as $h) {
                $habitude = new FicheEntretienHabitude();
                $habitude->setEntretien($entretien);
                $habitude->setType($h['type'] ?? $h['name'] ?? (is_string($h) ? $h : null));
                $habitude->setEstPresente($this->toBool($h['estPresente'] ?? $h['value'] ?? $h['present'] ?? null));
                $habitude->setQuantite($h['quantite'] ?? $h['details'] ?? null);
                $this->em->persist($habitude);
            }
        }

        $this->em->flush();
    }

    private function addExamenItems(FicheExamen $examen, array $items, string $categorie): void
    {
        foreach ($items as $item) {
            $entry = new FicheExamenItem();
            $entry->setExamen($examen);
            $entry->setCategorie($categorie);
            $entry->setLibelle($item['libelle'] ?? $item['label'] ?? $item['name'] ?? (is_string($item) ? $item : null));
            $entry->setEstPresent($this->toBool($item['estPresent'] ?? $item['value'] ?? $item['present'] ?? null));
            $entry->setDetails($item['details'] ?? $item['description'] ?? null);
            $this->em->persist($entry);
        }
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function addExamenItemsFromMap(FicheExamen $examen, array $map, string $categorie, bool $booleanValues): void
    {
        foreach ($map as $label => $value) {
            $entry = new FicheExamenItem();
            $entry->setExamen($examen);
            $entry->setCategorie($categorie);
            $entry->setLibelle((string) $label);
            if ($booleanValues) {
                $entry->setEstPresent($this->toBool($value));
                $entry->setDetails(null);
            } else {
                $entry->setEstPresent($value !== null && $value !== '');
                $entry->setDetails(is_scalar($value) ? (string) $value : null);
            }
            $this->em->persist($entry);
        }
    }

    public function updateExamens(int $ficheId, array $data): void
    {
        $fiche = $this->getFiche($ficheId);
        $examen = $this->getOrCreateExamen($fiche);

        $endobuccalFermee = $data['endobuccalBoucheFermee'] ?? null;
        if (is_array($endobuccalFermee)) {
            $examen->setOcclusion($endobuccalFermee['occlusion'] ?? null);
            $examen->setMediane($endobuccalFermee['mediane'] ?? null);
            $examen->setClassesAngle($endobuccalFermee['classesAngle'] ?? null);
            $examen->setVestibules($endobuccalFermee['vestibules'] ?? null);
        }

        $endobuccalOuverte = $data['endobuccalBoucheOuverte'] ?? null;
        if (is_array($endobuccalOuverte)) {
            $examen->setHbd($endobuccalOuverte['hbd'] ?? null);
            $examen->setBrossage($endobuccalOuverte['brossage'] ?? null);
            $examen->setSoccu($endobuccalOuverte['soccu'] ?? null);
            $examen->setCinematiqueMandibulaire($endobuccalOuverte['cinematiqueMandibulaire'] ?? null);
            $examen->setOuvertureBuccale($endobuccalOuverte['ouvertureBuccale'] ?? null);
            $examen->setTemperatureBuccale($endobuccalOuverte['temperatureBuccale'] ?? null);
            $examen->setAmplitudeOuverture($endobuccalOuverte['amplitudeOuverture'] ?? null);
            $examen->setBruitsArticulaires($endobuccalOuverte['bruitsArticulaires'] ?? null);
        }

        foreach ([
            'occlusion' => 'occlusion',
            'mediane' => 'mediane',
            'classesAngle' => 'classesAngle',
            'vestibules' => 'vestibules',
            'hbd' => 'hbd',
            'brossage' => 'brossage',
            'soccu' => 'soccu',
            'cinematiqueMandibulaire' => 'cinematiqueMandibulaire',
            'ouvertureBuccale' => 'ouvertureBuccale',
            'temperatureBuccale' => 'temperatureBuccale',
            'amplitudeOuverture' => 'amplitudeOuverture',
            'bruitsArticulaires' => 'bruitsArticulaires',
            'examenCanauxExcreteurs' => 'examenCanauxExcreteurs',
        ] as $inputKey => $setterKey) {
            if (array_key_exists($inputKey, $data)) {
                $method = 'set' . ucfirst($setterKey);
                $examen->{$method}($data[$inputKey]);
            }
        }

        if (isset($data['tissusMousTable']) && is_array($data['tissusMousTable'])) {
            $examen->setTissusMousTable($data['tissusMousTable']);
        }
        if (isset($data['tissusDursTable']) && is_array($data['tissusDursTable'])) {
            $examen->setTissusDursTable($data['tissusDursTable']);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $this->clearCollection($examen->getItems());
            foreach ($data['items'] as $item) {
                $entry = new FicheExamenItem();
                $entry->setExamen($examen);
                $entry->setCategorie($item['categorie'] ?? 'autre');
                $entry->setLibelle($item['libelle'] ?? $item['label'] ?? $item['name'] ?? null);
                $entry->setEstPresent($this->toBool($item['estPresent'] ?? $item['value'] ?? null));
                $entry->setDetails($item['details'] ?? $item['description'] ?? null);
                $this->em->persist($entry);
            }
        } else {
            $this->clearCollection($examen->getItems());
            if (isset($data['exobuccalInspection']) && is_array($data['exobuccalInspection'])) {
                if ($this->isAssoc($data['exobuccalInspection'])) {
                    $this->addExamenItemsFromMap($examen, $data['exobuccalInspection'], 'exobuccal_inspection', false);
                } else {
                    $this->addExamenItems($examen, $data['exobuccalInspection'], 'exobuccal_inspection');
                }
            }
            if (isset($data['exobuccalPalpation']) && is_array($data['exobuccalPalpation'])) {
                if ($this->isAssoc($data['exobuccalPalpation'])) {
                    $this->addExamenItemsFromMap($examen, $data['exobuccalPalpation'], 'exobuccal_palpation', false);
                } else {
                    $this->addExamenItems($examen, $data['exobuccalPalpation'], 'exobuccal_palpation');
                }
            }
            if (isset($data['chainesGanglionnaires']) && is_array($data['chainesGanglionnaires'])) {
                if ($this->isAssoc($data['chainesGanglionnaires'])) {
                    $this->addExamenItemsFromMap($examen, $data['chainesGanglionnaires'], 'chaines_ganglionnaires', true);
                } else {
                    $this->addExamenItems($examen, $data['chainesGanglionnaires'], 'chaines_ganglionnaires');
                }
            }
        }

        if (isset($data['examensLabo']) && is_array($data['examensLabo'])) {
            $this->clearCollection($examen->getExamensLabo());
            foreach ($data['examensLabo'] as $item) {
                $labo = new FicheExamenLabo();
                $labo->setExamen($examen);
                $labo->setType($item['type'] ?? $item['name'] ?? '');
                $labo->setObservation($item['observation'] ?? null);
                $labo->setResultat($item['resultat'] ?? null);
                $this->em->persist($labo);
            }
        } else {
            $this->clearCollection($examen->getExamensLabo());
            $map = [
                'Examens bacteriologiques' => $data['examensBacteriologiques'] ?? null,
                'Examens serologiques' => $data['examensSerologiques'] ?? null,
                'Examens histologiques' => $data['examensHistologiques'] ?? null,
            ];
            foreach ($map as $type => $payload) {
                if (!is_array($payload)) {
                    continue;
                }
                $labo = new FicheExamenLabo();
                $labo->setExamen($examen);
                $labo->setType($type);
                $labo->setObservation($payload['observation'] ?? null);
                $labo->setResultat($payload['resultat'] ?? null);
                $this->em->persist($labo);
            }
        }

        $this->em->flush();
    }

    public function updateBilans(int $ficheId, array $data): void
    {
        $fiche = $this->getFiche($ficheId);
        $bilan = $this->getOrCreateBilan($fiche);

        $bilanDentaire = $data['bilanDentaire'] ?? null;
        if (is_array($bilanDentaire) && isset($bilanDentaire['formuleDentaire']) && is_array($bilanDentaire['formuleDentaire'])) {
            $bilan->setFormuleDentaire($bilanDentaire['formuleDentaire']);
        }

        if (isset($data['formuleDentaire']) && is_array($data['formuleDentaire'])) {
            $bilan->setFormuleDentaire($data['formuleDentaire']);
        }

        $bilanRadiographique = $data['bilanRadiographique'] ?? null;
        if (is_array($bilanRadiographique)) {
            if (array_key_exists('radiographieExtraBuccaleHypothese', $bilanRadiographique)) {
                $bilan->setRadiographieExtraBuccaleHypothese($bilanRadiographique['radiographieExtraBuccaleHypothese']);
            }
            if (array_key_exists('radiographieIntraBuccaleHypothese', $bilanRadiographique)) {
                $bilan->setRadiographieIntraBuccaleHypothese($bilanRadiographique['radiographieIntraBuccaleHypothese']);
            }
        }

        $bilanSanguin = $data['bilanSanguin'] ?? null;
        if (is_array($bilanSanguin)) {
            if (array_key_exists('nfsDetaillee', $bilanSanguin)) {
                $bilan->setNfsDetaillee($bilanSanguin['nfsDetaillee']);
            }
            if (array_key_exists('tpTcaInr', $bilanSanguin)) {
                $bilan->setTpTcaInr($bilanSanguin['tpTcaInr']);
            }
            if (array_key_exists('uree', $bilanSanguin)) {
                $bilan->setUree($bilanSanguin['uree']);
            }
            if (array_key_exists('creatininemie', $bilanSanguin)) {
                $bilan->setCreatininemie($bilanSanguin['creatininemie']);
            }
            if (array_key_exists('glycemie', $bilanSanguin)) {
                $bilan->setGlycemie($bilanSanguin['glycemie']);
            }
        }

        if (array_key_exists('radiographieExtraBuccaleHypothese', $data)) {
            $bilan->setRadiographieExtraBuccaleHypothese($data['radiographieExtraBuccaleHypothese']);
        }
        if (array_key_exists('radiographieIntraBuccaleHypothese', $data)) {
            $bilan->setRadiographieIntraBuccaleHypothese($data['radiographieIntraBuccaleHypothese']);
        }
        if (array_key_exists('nfsDetaillee', $data)) {
            $bilan->setNfsDetaillee($data['nfsDetaillee']);
        }
        if (array_key_exists('tpTcaInr', $data)) {
            $bilan->setTpTcaInr($data['tpTcaInr']);
        }
        if (array_key_exists('uree', $data)) {
            $bilan->setUree($data['uree']);
        }
        if (array_key_exists('creatininemie', $data)) {
            $bilan->setCreatininemie($data['creatininemie']);
        }
        if (array_key_exists('glycemie', $data)) {
            $bilan->setGlycemie($data['glycemie']);
        }
        if (array_key_exists('diagnosticPositif', $data)) {
            $bilan->setDiagnosticPositif($data['diagnosticPositif']);
        }

        $this->em->flush();
    }

    public function updatePlanTraitement(int $ficheId, array $data): void
    {
        $fiche = $this->getFiche($ficheId);

        $plans = $data['plans'] ?? $data['planTraitement'] ?? [];
        if (!is_array($plans)) {
            $plans = [];
        }

        $this->clearCollection($fiche->getPlansTraitement());

        foreach ($plans as $planData) {
            $plan = new FichePlanTraitement();
            $plan->setFicheMedicale($fiche);
            $plan->setPlanIndex($planData['planIndex'] ?? null);
            $plan->setType($planData['type'] ?? '');
            $plan->setDescription($planData['description'] ?? $planData['Description'] ?? null);
            if (!empty($planData['dateSupposed'])) {
                $plan->setDateSupposed(new \DateTime($planData['dateSupposed']));
            }
            $this->em->persist($plan);
        }

        $this->em->flush();
    }

    public function updateDocuments(int $ficheId, array $data, array $files = []): void
    {
        $fiche = $this->getFiche($ficheId);

        $this->clearCollection($fiche->getDocuments());

        $fs = new Filesystem();
        $uploadDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'fiche-medicale';

        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0775);
            $fs->chmod($uploadDir, 0775);
        }

        $documents = $data['documents'] ?? [];
        if (!is_array($documents)) {
            $documents = [];
        }

        foreach ($documents as $index => $doc) {
            $type = $doc['type'] ?? 'Document';
            $libelle = $doc['libelle'] ?? 'document';
            $groupKey = $doc['groupKey'] ?? null;
            if (!$groupKey) {
                $groupKey = uniqid('doc_', true);
            }

            $urls = $doc['urls'] ?? (isset($doc['url']) ? [$doc['url']] : []);
            if (is_array($urls)) {
                foreach ($urls as $url) {
                    if (!$url) {
                        continue;
                    }
                    $entity = new FicheDocument();
                    $entity->setFicheMedicale($fiche);
                    $entity->setType($type);
                    $entity->setLibelle($libelle);
                    $entity->setUrl($url);
                    $entity->setGroupKey($groupKey);
                    $this->em->persist($entity);
                }
            }

            $docFiles = $files[$index] ?? [];
            if ($docFiles instanceof UploadedFile) {
                $docFiles = [$docFiles];
            }

            if (!is_array($docFiles)) {
                $docFiles = [];
            }

            foreach ($docFiles as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

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

                $entity = new FicheDocument();
                $entity->setFicheMedicale($fiche);
                $entity->setType($type);
                $entity->setLibelle($libelle);
                $entity->setUrl('uploads/documents/fiche-medicale/' . $movedFile->getFilename());
                $entity->setGroupKey($groupKey);
                $this->em->persist($entity);
            }
        }

        $this->em->flush();
    }

    public function updateDevis(int $ficheId, array $data): void
    {
        $fiche = $this->getFiche($ficheId);

        $type = $data['type'] ?? 0;
        $devis = $this->devisRepo->findOneBy(['ficheMedicale' => $fiche, 'type' => $type]);
        if (!$devis) {
            $devis = new Devis();
        }

        $devis->setFicheMedicale($fiche);
        if (!empty($data['date'])) {
            $devis->setDate(new \DateTime($data['date']));
        } elseif (!$devis->getDate()) {
            $devis->setDate(new \DateTime('now'));
        }
        $devis->setType((int) $type);
        if (array_key_exists('statut', $data)) {
            $devis->setStatut((int) $data['statut']);
        }

        $devis->setMontant((float) ($data['montant'] ?? 0));
        if (array_key_exists('reste', $data)) {
            $devis->setReste((float) $data['reste']);
        }

        foreach ($devis->getContenus() as $contenu) {
            $devis->removeContenu($contenu);
            $this->em->remove($contenu);
        }

        $amount = 0;
        if (isset($data['contenus']) && is_array($data['contenus'])) {
            foreach ($data['contenus'] as $c) {
                $cd = new ContenuDevis();
                $cd->setDevis($devis)
                    ->setDesignation($c['designation'] ?? '')
                    ->setQte($c['qte'] ?? 1)
                    ->setMontant($c['montant'] ?? 0);
                $amount += $cd->getMontant() * $cd->getQte();
                $cd->setMontantTotal($amount);
                $this->em->persist($cd);
            }
        }

        $devis->setMontant($amount ?: $devis->getMontant());
        $this->em->persist($devis);
        $this->em->flush();
    }

    public function getFicheJson(int $ficheId): array
    {
        $fiche = $this->getFiche($ficheId);
        $patient = $fiche->getPatient();

        $entretien = $fiche->getEntretien();
        $examen = $fiche->getExamen();
        $bilan = $fiche->getBilan();

        $entretienData = null;
        if ($entretien) {
            $entretienData = [
                'motifConsultation' => $entretien->getMotifConsultation(),
                'anamnese' => $entretien->getAnamnese(),
                'etatGynecologique' => [
                    'allaitement' => $entretien->getAllaitement(),
                    'grossesseEnCours' => $entretien->getGrossesseEnCours(),
                    'menstrues' => $entretien->getMenstrues(),
                ],
                'medicaments' => array_map(static fn(FicheEntretienMedicament $m) => [
                    'id' => $m->getId(),
                    'nom' => $m->getNom(),
                    'estUtilise' => $m->getEstUtilise(),
                    'details' => $m->getDetails(),
                ], $entretien->getMedicaments()->toArray()),
                'affections' => array_map(static fn(FicheEntretienAffection $a) => [
                    'id' => $a->getId(),
                    'nom' => $a->getNom(),
                    'estPresente' => $a->getEstPresente(),
                    'details' => $a->getDetails(),
                ], $entretien->getAffections()->toArray()),
                'questions' => array_map(static fn(FicheEntretienQuestion $q) => [
                    'id' => $q->getId(),
                    'question' => $q->getQuestion(),
                    'reponse' => $q->getReponse(),
                    'precision' => $q->getPrecision(),
                ], $entretien->getQuestions()->toArray()),
                'habitudes' => array_map(static fn(FicheEntretienHabitude $h) => [
                    'id' => $h->getId(),
                    'type' => $h->getType(),
                    'estPresente' => $h->getEstPresente(),
                    'quantite' => $h->getQuantite(),
                ], $entretien->getHabitudes()->toArray()),
            ];
        }

        $examenData = null;
        if ($examen) {
            $items = $examen->getItems()->toArray();
            $exobuccalInspection = [];
            $exobuccalPalpation = [];
            $chainesGanglionnaires = [];
            foreach ($items as $item) {
                $categorie = $item->getCategorie();
                if ($categorie === 'exobuccal_inspection') {
                    $exobuccalInspection[$item->getLibelle()] = $item->getDetails();
                }
                if ($categorie === 'exobuccal_palpation') {
                    $exobuccalPalpation[$item->getLibelle()] = $item->getDetails();
                }
                if ($categorie === 'chaines_ganglionnaires') {
                    $chainesGanglionnaires[$item->getLibelle()] = $item->getEstPresent();
                }
            }

            $examensLabo = $examen->getExamensLabo()->toArray();
            $laboMap = [
                'Examens bacteriologiques' => null,
                'Examens serologiques' => null,
                'Examens histologiques' => null,
            ];
            foreach ($examensLabo as $labo) {
                $type = $labo->getType();
                if (array_key_exists($type, $laboMap)) {
                    $laboMap[$type] = [
                        'observation' => $labo->getObservation(),
                        'resultat' => $labo->getResultat(),
                    ];
                }
            }

            $examenData = [
                'exobuccalInspection' => $exobuccalInspection,
                'exobuccalPalpation' => $exobuccalPalpation,
                'chainesGanglionnaires' => $chainesGanglionnaires,
                'endobuccalBoucheFermee' => [
                    'occlusion' => $examen->getOcclusion(),
                    'mediane' => $examen->getMediane(),
                    'classesAngle' => $examen->getClassesAngle(),
                    'vestibules' => $examen->getVestibules(),
                ],
                'endobuccalBoucheOuverte' => [
                    'hbd' => $examen->getHbd(),
                    'brossage' => $examen->getBrossage(),
                    'soccu' => $examen->getSoccu(),
                    'cinematiqueMandibulaire' => $examen->getCinematiqueMandibulaire(),
                    'ouvertureBuccale' => $examen->getOuvertureBuccale(),
                    'temperatureBuccale' => $examen->getTemperatureBuccale(),
                    'amplitudeOuverture' => $examen->getAmplitudeOuverture(),
                    'bruitsArticulaires' => $examen->getBruitsArticulaires(),
                ],
                'tissusMousTable' => $examen->getTissusMousTable(),
                'tissusDursTable' => $examen->getTissusDursTable(),
                'examenCanauxExcreteurs' => $examen->getExamenCanauxExcreteurs(),
                'examensBacteriologiques' => $laboMap['Examens bacteriologiques'] ?? ['observation' => null, 'resultat' => null],
                'examensSerologiques' => $laboMap['Examens serologiques'] ?? ['observation' => null, 'resultat' => null],
                'examensHistologiques' => $laboMap['Examens histologiques'] ?? ['observation' => null, 'resultat' => null],
            ];
        }

        $bilanData = null;
        if ($bilan) {
            $bilanData = [
                'bilanDentaire' => [
                    'formuleDentaire' => $bilan->getFormuleDentaire(),
                ],
                'bilanRadiographique' => [
                    'radiographieExtraBuccaleHypothese' => $bilan->getRadiographieExtraBuccaleHypothese(),
                    'radiographieIntraBuccaleHypothese' => $bilan->getRadiographieIntraBuccaleHypothese(),
                ],
                'bilanSanguin' => [
                    'nfsDetaillee' => $bilan->getNfsDetaillee(),
                    'tpTcaInr' => $bilan->getTpTcaInr(),
                    'uree' => $bilan->getUree(),
                    'creatininemie' => $bilan->getCreatininemie(),
                    'glycemie' => $bilan->getGlycemie(),
                ],
                'diagnosticPositif' => $bilan->getDiagnosticPositif(),
            ];
        }

        $plans = array_map(static fn(FichePlanTraitement $p) => [
            'id' => $p->getId(),
            'planIndex' => $p->getPlanIndex(),
            'type' => $p->getType(),
            'dateSupposed' => $p->getDateSupposed()?->format('Y-m-d'),
            'description' => $p->getDescription(),
        ], $fiche->getPlansTraitement()->toArray());

        $documentsMap = [];
        foreach ($fiche->getDocuments()->toArray() as $doc) {
            if (!$doc instanceof FicheDocument) {
                continue;
            }
            $groupKey = $doc->getGroupKey() ?: ('legacy-' . $doc->getId());
            if (!isset($documentsMap[$groupKey])) {
                $documentsMap[$groupKey] = [
                    'groupKey' => $groupKey,
                    'type' => $doc->getType(),
                    'libelle' => $doc->getLibelle(),
                    'urls' => []
                ];
            }
            $documentsMap[$groupKey]['urls'][] = $doc->getUrl();
        }
        $documents = array_values($documentsMap);

        $devis = array_map(static fn(Devis $d) => [
            'id' => $d->getId(),
            'date' => $d->getDate()?->format('Y-m-d'),
            'type' => $d->getType(),
            'statut' => $d->getStatut() ?? 0,
            'montant' => $d->getMontant() ?? 0.0,
            'reste' => $d->getReste() ?? 0.0,
            'contenus' => array_map(static fn(ContenuDevis $c) => [
                'id' => $c->getId(),
                'designation' => $c->getDesignation(),
                'qte' => $c->getQte(),
                'montant' => $c->getMontant(),
                'montantTotal' => $c->getMontantTotal(),
            ], $d->getContenus()->toArray()),
        ], $fiche->getDevis()->toArray());

        $consultations = array_map(static fn($c) => [
            'id' => $c->getId(),
            'type' => $c->getType(),
            'noteSeance' => $c->getNoteSeance(),
            'createdAt' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'medecin' => $c->getMedecin() ? [
                'id' => $c->getMedecin()->getId(),
                'name' => $c->getMedecin()->getFullName(),
            ] : null,
        ], $fiche->getConsultations()->toArray());

        return [
            'id' => $fiche->getId(),
            'createdAt' => $fiche->getCreatedAt()?->format('Y-m-d H:i:s'),
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
            'entretien' => $entretienData,
            'examens' => $examenData,
            'bilans' => $bilanData,
            'planTraitement' => $plans,
            'documents' => $documents,
            'devis' => $devis,
            'consultations' => $consultations,
        ];
    }
}
