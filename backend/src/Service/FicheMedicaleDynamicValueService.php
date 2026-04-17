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
use App\Entity\FicheMedicaleValeur;
use App\Entity\FichePlanTraitement;
use App\Entity\FormulaireChamp;
use Doctrine\ORM\EntityManagerInterface;

class FicheMedicaleDynamicValueService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MedicalFormService $medicalFormService,
    ) {
    }

    public function syncDynamicValues(FicheMedicale $fiche, bool $flush = true): void
    {
        $this->medicalFormService->initializeFicheMedicale($fiche);

        $valueMap = $this->buildValueMap($fiche);
        $existingByCode = [];
        foreach ($fiche->getValeursDynamiques() as $valeur) {
            $existingByCode[$valeur->getChampCode()] = $valeur;
        }

        $champByCode = $this->buildChampByCode($fiche);

        foreach ($valueMap as $champCode => $value) {
            $entity = $existingByCode[$champCode] ?? new FicheMedicaleValeur();
            $entity
                ->setFicheMedicale($fiche)
                ->setChampCode($champCode)
                ->setChamp($champByCode[$champCode] ?? null)
                ->setValeur($value);

            if (!isset($existingByCode[$champCode])) {
                $fiche->addValeurDynamique($entity);
                $this->em->persist($entity);
            }

            unset($existingByCode[$champCode]);
        }

        foreach ($existingByCode as $obsolete) {
            $fiche->removeValeurDynamique($obsolete);
            $this->em->remove($obsolete);
        }

        if ($flush) {
            $this->em->flush();
        }
    }

    /** @return array<string, mixed> */
    public function buildValueMap(FicheMedicale $fiche): array
    {
        return [
            'entretien_motif_consultation' => $fiche->getEntretien()?->getMotifConsultation(),
            'entretien_anamnese' => $fiche->getEntretien()?->getAnamnese(),
            'entretien_etat_gynecologique' => $this->buildEtatGynecologique($fiche->getEntretien()),
            'entretien_medicaments' => $this->buildMedicaments($fiche->getEntretien()),
            'entretien_affections' => $this->buildAffections($fiche->getEntretien()),
            'entretien_questions' => $this->buildQuestions($fiche->getEntretien()),
            'entretien_habitudes' => $this->buildHabitudes($fiche->getEntretien()),
            'examens_exobuccal_inspection' => $this->buildExamenItemsByCategorie($fiche->getExamen(), 'exobuccal_inspection', false),
            'examens_exobuccal_palpation' => $this->buildExamenItemsByCategorie($fiche->getExamen(), 'exobuccal_palpation', false),
            'examens_chaines_ganglionnaires' => $this->buildExamenItemsByCategorie($fiche->getExamen(), 'chaines_ganglionnaires', true),
            'examens_endobuccal_bouche_fermee' => $this->buildEndobuccalBoucheFermee($fiche->getExamen()),
            'examens_endobuccal_bouche_ouverte' => $this->buildEndobuccalBoucheOuverte($fiche->getExamen()),
            'examens_tissus_mous' => $fiche->getExamen()?->getTissusMousTable() ?? [],
            'examens_tissus_durs' => $fiche->getExamen()?->getTissusDursTable() ?? [],
            'examens_canaux_excreteurs' => $fiche->getExamen()?->getExamenCanauxExcreteurs(),
            'examens_bacteriologiques' => $this->buildLaboValue($fiche->getExamen(), 'Examens bacteriologiques'),
            'examens_serologiques' => $this->buildLaboValue($fiche->getExamen(), 'Examens serologiques'),
            'examens_histologiques' => $this->buildLaboValue($fiche->getExamen(), 'Examens histologiques'),
            'bilans_bilan_dentaire' => $this->buildBilanDentaire($fiche->getBilan()),
            'bilans_bilan_radiographique' => $this->buildBilanRadiographique($fiche->getBilan()),
            'bilans_bilan_sanguin' => $this->buildBilanSanguin($fiche->getBilan()),
            'bilans_diagnostic_positif' => $fiche->getBilan()?->getDiagnosticPositif(),
            'documents_collection' => $this->buildDocuments($fiche),
            'plan_traitement_collection' => $this->buildPlanTraitement($fiche),
        ] + $this->buildDevisValues($fiche);
    }

    /** @return array<string, FormulaireChamp> */
    private function buildChampByCode(FicheMedicale $fiche): array
    {
        $champByCode = [];
        $formulaire = $fiche->getFormulaire();
        if ($formulaire === null) {
            return $champByCode;
        }

        foreach ($formulaire->getOnglets() as $onglet) {
            foreach ($onglet->getSections() as $section) {
                foreach ($section->getChamps() as $champ) {
                    $champByCode[$champ->getCode()] = $champ;
                }
            }
        }

        return $champByCode;
    }

    private function buildEtatGynecologique(?FicheEntretien $entretien): array
    {
        return [
            'allaitement' => $entretien?->getAllaitement(),
            'grossesseEnCours' => $entretien?->getGrossesseEnCours(),
            'menstrues' => $entretien?->getMenstrues(),
        ];
    }

    private function buildMedicaments(?FicheEntretien $entretien): array
    {
        if ($entretien === null) {
            return [];
        }

        return array_values(array_map(static fn(FicheEntretienMedicament $medicament): array => [
            'id' => $medicament->getId(),
            'nom' => $medicament->getNom(),
            'estUtilise' => $medicament->getEstUtilise(),
            'details' => $medicament->getDetails(),
        ], $entretien->getMedicaments()->toArray()));
    }

    private function buildAffections(?FicheEntretien $entretien): array
    {
        if ($entretien === null) {
            return [];
        }

        return array_values(array_map(static fn(FicheEntretienAffection $affection): array => [
            'id' => $affection->getId(),
            'nom' => $affection->getNom(),
            'estPresente' => $affection->getEstPresente(),
            'details' => $affection->getDetails(),
        ], $entretien->getAffections()->toArray()));
    }

    private function buildQuestions(?FicheEntretien $entretien): array
    {
        if ($entretien === null) {
            return [];
        }

        return array_values(array_map(static fn(FicheEntretienQuestion $question): array => [
            'id' => $question->getId(),
            'question' => $question->getQuestion(),
            'reponse' => $question->getReponse(),
            'precision' => $question->getPrecision(),
        ], $entretien->getQuestions()->toArray()));
    }

    private function buildHabitudes(?FicheEntretien $entretien): array
    {
        if ($entretien === null) {
            return [];
        }

        return array_values(array_map(static fn(FicheEntretienHabitude $habitude): array => [
            'id' => $habitude->getId(),
            'type' => $habitude->getType(),
            'estPresente' => $habitude->getEstPresente(),
            'quantite' => $habitude->getQuantite(),
        ], $entretien->getHabitudes()->toArray()));
    }

    private function buildExamenItemsByCategorie(?FicheExamen $examen, string $categorie, bool $booleanValues): array
    {
        if ($examen === null) {
            return [];
        }

        $values = [];
        foreach ($examen->getItems() as $item) {
            if ($item->getCategorie() !== $categorie) {
                continue;
            }

            $values[$item->getLibelle()] = $booleanValues ? $item->getEstPresent() : $item->getDetails();
        }

        return $values;
    }

    private function buildEndobuccalBoucheFermee(?FicheExamen $examen): array
    {
        return [
            'occlusion' => $examen?->getOcclusion(),
            'mediane' => $examen?->getMediane(),
            'classesAngle' => $examen?->getClassesAngle(),
            'vestibules' => $examen?->getVestibules(),
        ];
    }

    private function buildEndobuccalBoucheOuverte(?FicheExamen $examen): array
    {
        return [
            'hbd' => $examen?->getHbd(),
            'brossage' => $examen?->getBrossage(),
            'soccu' => $examen?->getSoccu(),
            'cinematiqueMandibulaire' => $examen?->getCinematiqueMandibulaire(),
            'ouvertureBuccale' => $examen?->getOuvertureBuccale(),
            'temperatureBuccale' => $examen?->getTemperatureBuccale(),
            'amplitudeOuverture' => $examen?->getAmplitudeOuverture(),
            'bruitsArticulaires' => $examen?->getBruitsArticulaires(),
        ];
    }

    private function buildLaboValue(?FicheExamen $examen, string $type): array
    {
        if ($examen === null) {
            return ['observation' => null, 'resultat' => null];
        }

        foreach ($examen->getExamensLabo() as $labo) {
            if ($labo->getType() === $type) {
                return [
                    'observation' => $labo->getObservation(),
                    'resultat' => $labo->getResultat(),
                ];
            }
        }

        return ['observation' => null, 'resultat' => null];
    }

    private function buildBilanDentaire(?FicheBilan $bilan): array
    {
        return [
            'formuleDentaire' => $bilan?->getFormuleDentaire() ?? [],
        ];
    }

    private function buildBilanRadiographique(?FicheBilan $bilan): array
    {
        return [
            'radiographieExtraBuccaleHypothese' => $bilan?->getRadiographieExtraBuccaleHypothese(),
            'radiographieIntraBuccaleHypothese' => $bilan?->getRadiographieIntraBuccaleHypothese(),
        ];
    }

    private function buildBilanSanguin(?FicheBilan $bilan): array
    {
        return [
            'nfsDetaillee' => $bilan?->getNfsDetaillee(),
            'tpTcaInr' => $bilan?->getTpTcaInr(),
            'uree' => $bilan?->getUree(),
            'creatininemie' => $bilan?->getCreatininemie(),
            'glycemie' => $bilan?->getGlycemie(),
        ];
    }

    private function buildPlanTraitement(FicheMedicale $fiche): array
    {
        return array_values(array_map(static fn(FichePlanTraitement $plan): array => [
            'id' => $plan->getId(),
            'planIndex' => $plan->getPlanIndex(),
            'type' => $plan->getType(),
            'dateSupposed' => $plan->getDateSupposed()?->format('Y-m-d'),
            'description' => $plan->getDescription(),
        ], $fiche->getPlansTraitement()->toArray()));
    }

    private function buildDocuments(FicheMedicale $fiche): array
    {
        $documentsMap = [];
        foreach ($fiche->getDocuments() as $document) {
            if (!$document instanceof FicheDocument) {
                continue;
            }

            $groupKey = $document->getGroupKey() ?: ('legacy-' . $document->getId());
            if (!isset($documentsMap[$groupKey])) {
                $documentsMap[$groupKey] = [
                    'groupKey' => $groupKey,
                    'type' => $document->getType(),
                    'libelle' => $document->getLibelle(),
                    'urls' => [],
                ];
            }

            $documentsMap[$groupKey]['urls'][] = $document->getUrl();
        }

        return array_values($documentsMap);
    }

    /** @return array<string, mixed> */
    private function buildDevisValues(FicheMedicale $fiche): array
    {
        $values = [];
        foreach ($fiche->getDevis() as $devis) {
            if (!$devis instanceof Devis) {
                continue;
            }

            $values['devis_' . $devis->getType()] = [
                'id' => $devis->getId(),
                'date' => $devis->getDate()?->format('Y-m-d'),
                'type' => $devis->getType(),
                'statut' => $devis->getStatut(),
                'montant' => $devis->getMontant(),
                'reste' => $devis->getReste(),
                'contenus' => array_map(static fn(ContenuDevis $contenu): array => [
                    'id' => $contenu->getId(),
                    'designation' => $contenu->getDesignation(),
                    'qte' => $contenu->getQte(),
                    'montant' => $contenu->getMontant(),
                    'montantTotal' => $contenu->getMontantTotal(),
                ], $devis->getContenus()->toArray()),
            ];
        }

        return $values;
    }
}