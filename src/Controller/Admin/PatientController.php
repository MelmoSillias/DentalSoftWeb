<?php

namespace App\Controller\Admin;

use App\Entity\Consultation;
use App\Entity\Patient;
use App\Entity\Rdv;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Salle; // Replace with actual entities used
use App\Repository\SalleRepository; // Replace with actual repositories used
use App\Form\PatientType; // Replace with actual forms used

final class PatientController extends AbstractController
{
#[Route('/admin/patients', name: 'app_admin_patient')]
    public function patient(SalleRepository $salleRepository): Response
    {
        $salles = $salleRepository->findAll();

        return $this->render('admin/patient.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'patients',
            'salles' => $salles // Ajout des salles
        ]);
    }

    #[Route('/api/patient/{id}/dossier', name: 'api_patient_dossier_get', methods: ['GET'])]
    public function getDossier(int $id, EntityManagerInterface $em): JsonResponse
    {
        $patient = $em->getRepository(Patient::class)->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Patient introuvable'], 404);
        }

        // Champs simples
        $data = [
            'patient' => [
                'nom'           => $patient->getNom(),
                'prenom'        => $patient->getPrenom(),
                'dateNaissance' => $patient->getDateNaissance()?->format(\DateTime::ISO8601),
                'sexe'          => $patient->getSexe(),
                'telephone'     => $patient->getTelephone(),
                'adresse'       => $patient->getAdresse(),
                'groupeSanguin' => $patient->getGroupeSanguin()
            ],
            // Collections composées
            'allergies'       => [],
            'antecedents'     => [],
            'contactsUrgence' => [],
            // Consultations et RDV
            'consultations'   => [],
            'rdvs'            => [],
        ];

        // Allergies
        foreach ($patient->getAllergies() as $a) {
            $data['allergies'][] = [
                'id'          => $a->getId(),
                'nom'         => $a->getNom(),
                'description' => $a->getDescription(),
            ];
        }

        // Antécédents
        foreach ($patient->getAntecedents() as $ant) {
            $data['antecedents'][] = [
                'id'          => $ant->getId(),
                'nom'         => $ant->getNom(),
                'description' => $ant->getDescription(),
            ];
        }

        // Contacts d’urgence
        foreach ($patient->getContactsUrgence() as $c) {
            $data['contactsUrgence'][] = [
                'id'        => $c->getId(),
                'nom'       => $c->getNom(),
                'prenom'    => $c->getPrenom(),
                'relation'  => $c->getRelation(),
                'telephone' => $c->getTelephone(),
            ];
        }

        // Consultations (avec détails imbriqués)
        foreach ($patient->getConsultations() as $c) {
            $consultData = [
                'id'          => $c->getId(),
                'dateDebut'   => $c->getDateDebut()->format(\DateTime::ATOM),
                'medecinNom'  => $c->getMedecin()->getNom(), 
                'state'       => $c->getState(),
                'diagnostic'  => $c->getDiagnostic(),
                'remarques'   => $c->getRemarques(),
                'documents'   => [],
                'ordonnances' => [],
            ];
            foreach ($c->getDocuments() as $d) {
                $consultData['documents'][] = [
                    'libelle' => $d->getLibelle(),
                    'date'    => $d->getDate()->format(\DateTime::ISO8601),
                ];
            }
            foreach ($c->getOrdonnances() as $o) {
                $consultData['ordonnances'][] = [
                    'libelle' => $o->getLibelle(),
                    'date'    => $o->getDate()->format(\DateTime::ISO8601),
                ];
            }
            $data['consultations'][] = $consultData;
        }

        // RDV
        $rdvRepo = $em->getRepository(Rdv::class);
        foreach ($rdvRepo->findBy(['patient' => $patient]) as $r) {
            $data['rdvs'][] = [
                'id'        => $r->getId(),
                'dateHeure' => $r->getDateRdv()->format(\DateTime::ISO8601),
                'salle'     => $r->getSalle()->getNom(),
                'medecinNom'=> $r->getMedecin()->getNom(),
                'patientNom'=> $patient->getNom() . ' ' . $patient->getPrenom(),
                'statut'    => $r->getStatut(),
            ];
        }

        return $this->json($data);
    }

    /**
     * PUT /api/patient/{id}/dossier
     * Met à jour les champs simples et collections du dossier patient
     */
    #[Route('/api/patient/{id}/dossier', name: 'api_patient_dossier_update', methods: ['PUT'])]
    public function updateDossier(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $patient = $em->getRepository(Patient::class)->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Patient introuvable'], 404);
        }

        // Champs simples
        foreach ($payload['patient'] as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($patient, $setter)) {
                $patient->$setter($value);
            }
        }

        // Collections composées : on vide et on rebâtit
        $patient->getAllergies()->clear();
        foreach ($payload['allergies'] as $a) {
            $entity = new \App\Entity\Allergy();
            if (!empty($a['id'])) {
                // si existant, on récupère l’entité en BDD au lieu de créer
                $entity = $em->getRepository(\App\Entity\Allergy::class)->find($a['id']) ?: $entity;
            }
            $entity->setNom($a['nom'])
                   ->setDescription($a['description']);
            $patient->addAllergy($entity);
        }

        $patient->getAntecedents()->clear();
        foreach ($payload['antecedents'] as $ant) {
            $entity = new \App\Entity\Antecedent();
            if (!empty($ant['id'])) {
                $entity = $em->getRepository(\App\Entity\Antecedent::class)->find($ant['id']) ?: $entity;
            }
            $entity->setNom($ant['nom'])
                   ->setDescription($ant['description']);
            $patient->addAntecedent($entity);
        }

        $patient->getContactsUrgence()->clear();
        foreach ($payload['contactsUrgence'] as $c) {
            $entity = new \App\Entity\ContactUrgence();
            if (!empty($c['id'])) {
                $entity = $em->getRepository(\App\Entity\ContactUrgence::class)->find($c['id']) ?: $entity;
            }
            $entity->setNom($c['nom'])
                   ->setPrenom($c['prenom'])
                   ->setRelation($c['relation'])
                   ->setTelephone($c['telephone']);
            $patient->addContactUrgence($entity);
        }

        $em->persist($patient);
        $em->flush();

        return $this->json(['success' => true]);
    }

    
}
