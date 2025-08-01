<?php

namespace App\Controller\Admin;

use App\Entity\ContenuDevis;
use App\Entity\Devis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Facture;
use App\Entity\PaiementDevis;
use App\Entity\Transaction;
use App\Repository\FactureRepository;
use App\Form\FactureType;
use App\Repository\DevisRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\PaiementDevisRepository;

final class CaisseController extends AbstractController
{
    #[Route('/admin/caisse', name: 'app_admin_caisse')]
    public function caisse(): Response
    {
        return $this->render('admin/caisse.html.twig', [
            'active_page' => 'caisse'
        ]);
    }

    #[Route('/api/devis/all', name: 'api_devis_all', methods: ['GET'])]
    public function getDevisAll(Request $request, DevisRepository $repo): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end   = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        $devis = $repo->createQueryBuilder('d')
            ->join('d.fiche', 'f')->addSelect('f')
            ->join('f.patient', 'p')->addSelect('p')
            ->where('d.type = :type')
            ->AndWhere('d.date BETWEEN :start AND :end')
            ->setParameter('type', 1)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('d.date', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (Devis $d) {
            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'statut' => $d->getStatut(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $d->getFiche()->getPatient()->getNom(),
                    'prenom' => $d->getFiche()->getPatient()->getPrenom(),

                ],
                'telephone' => $d->getFiche()->getPatient()->getTelephone(),
            ];
        }, $devis);

        return new JsonResponse($data);
    }

    #[Route('/api/devis/impayes', name: 'api_devis_impayes', methods: ['GET'])]
    public function getDevisImpayes(DevisRepository $repo): JsonResponse
    {
        $devis = $repo->createQueryBuilder('d')
            ->join('d.fiche', 'f')->addSelect('f')
            ->join('f.patient', 'p')->addSelect('p')
            ->where('d.statut = 0')
            ->Andwhere('d.type = 1')
            ->orderBy('d.date', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (Devis $d) {
            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $d->getFiche()->getPatient()->getNom(),
                    'prenom' => $d->getFiche()->getPatient()->getPrenom(),
                ],
                'telephone' => $d->getFiche()->getPatient()->getTelephone(),
            ];
        }, $devis);

        return new JsonResponse($data);
    }

    #[Route('/api/paiements-devis', name: 'api_paiements_devis', methods: ['GET'])]
    public function getPaiementsDevis(Request $request, PaiementDevisRepository $repo): JsonResponse
    {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end   = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        $paiements = $repo->createQueryBuilder('p')
            ->leftJoin('p.devis', 'd')->addSelect('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'pat')->addSelect('pat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (PaiementDevis $p) {
            $devis = $p->getDevis();

            if ($p->getConsultation()) {
                $patient = $p->getConsultation()->getPatient();
            } elseif ($p->getDevis() && $p->getDevis()->getFiche() && $p->getDevis()->getFiche()->getPatient()) {
                $patient = $p->getDevis()->getFiche()->getPatient();
            } else {
                $patient = null;
            }

            return [
                'devisId' => $devis ? $devis->getId() : $p->getId(),
                'patient' => $patient ? $patient->getFullName() : 'Anonyme',
                'telephone' => $patient->getTelephone(),
                'montant' => $p->getMontant(),
                'mode'    => $p->getMode()->getLibelle(),
                'date'    => $p->getDate()->format('Y-m-d H:i:s'),
                'type'    => $devis ? 'devis' : 'ticket',
                'pId' => $p->getId()
            ];
        }, $paiements);

        return new JsonResponse(['data' => $data]);
    }


    #[Route('/api/devis/{id}/preview', name: 'api_devis_preview', methods: ['GET'])]
    public function previewDevis(int $id, DevisRepository $repo): JsonResponse
    {
        $devis = $repo->find($id);
        if (!$devis) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        $fiche = $devis->getFiche();
        $patient = $fiche->getPatient();
        $contenus = $devis->getContenus();

        $contenuArr = array_map(function (ContenuDevis $c) {
            return [
                'designation' => $c->getDesignation(),
                'qte' => $c->getQte(),
                'montant' => $c->getMontant(),
                'total' => $c->getQte() * $c->getMontant()
            ];
        }, $contenus->toArray());

        return new JsonResponse([
            'id' => $devis->getId(),
            'date' => $devis->getDate()->format('Y-m-d'),
            'montant' => $devis->getMontant(),
            'reste' => $devis->getReste(),
            'patient' => [
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'telephone' => $patient->getTelephone()
            ],
            'contenus' => $contenuArr
        ]);
    }

    #[Route('/api/devis/{id}/payer', name: 'api_devis_payer', methods: ['POST'])]
    public function payerDevis(
        int $id,
        Request $request,
        DevisRepository $repo,
        EntityManagerInterface $em,
        ModeDePaiementRepository $modeRepo
    ): JsonResponse {
        $devis = $repo->find($id);
        if (!$devis) {
            return new JsonResponse(['error' => 'Devis introuvable'], 404);
        }

        if ($devis->getReste() > 0) {

            $mode = $modeRepo->find($request->request->get('modeId'));
            $montant = floatval($request->request->get('montant'));

            if (!$mode || $montant <= 0 || $montant > $devis->getReste()) {
                return new JsonResponse(['error' => 'Données invalides'], 400);
            }

            $paiement = new PaiementDevis();
            $paiement->setDevis($devis);
            $paiement->setMode($mode);
            $paiement->setMontant($montant);
            $paiement->setDate(new \DateTime());

            $devis->setReste($devis->getReste() - $montant);
            if ($devis->getReste() <= 0) {
                $devis->setReste(0);
                $devis->setStatut(1);
            }

            // Créer la transaction associée
            $transaction = new Transaction();
            $transaction->setType('Entrée');
            $transaction->setMontant($paiement->getMontant());
            $transaction->setDateTransaction(new \DateTime());
            $transaction->setDescription('Paiement de la facture | Devis #' . $devis->getId());
            $transaction->setModeDePaiement($mode);
            $transaction->setPaiementDevis($paiement);

            $em->persist($transaction);

            $em->persist($paiement);
            $em->persist($devis);
        } else {
            $devis->setStatut(1);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/paiement-devis/impression', name: 'api_paiements_devis_print', methods: ['GET'])]
    public function printListePaiements(
        Request $request,
        PaiementDevisRepository $repo
    ): Response {
        $start = new \DateTime($request->query->get('start', 'today'));
        $end = new \DateTime($request->query->get('end', 'today'));
        $end->setTime(23, 59, 59);

        $paiements = $repo->createQueryBuilder('p')
            ->leftJoin('p.devis', 'd')->addSelect('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'pat')->addSelect('pat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('devis/print_paiements_liste.html.twig', [
            'paiements' => $paiements,
            'start' => $start,
            'end' => $end
        ]);
    }

    #[Route('/api/paiement-devis/{id}/print', name: 'api_paiement_devis_print', methods: ['GET'])]
    public function printPaiement(int $id, PaiementDevisRepository $repo): Response
    {
        $paiement = $repo->find($id);
        if (!$paiement) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        return $this->render('devis/print_paiement.html.twig', [
            'paiement' => $paiement
        ]);
    }

    #[Route('/api/paiement-ticket/{id}/print', name: 'api_paiement_ticket_print', methods: ['GET'])]
    public function printTicket(int $id, PaiementDevisRepository $repo): Response
    {
        $paiement = $repo->find($id);
        if (!$paiement) {
            throw $this->createNotFoundException('Paiement introuvable.');
        }

        return $this->render('devis/print_Ticket.html.twig', [
            'paiement' => $paiement
        ]);
    }
}
