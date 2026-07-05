<?php

namespace App\Reporting\Controller\Api\Report;

use App\IdentityAccess\Entity\Employe;
use App\Reporting\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/report', name: 'api_report_')]
class MedecinController extends AbstractController
{
    public function __construct(
        private ReportService $reportService,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/medecin', name: 'api_medecin_dashboard', methods: ['GET'])]
    public function medecinDashboard(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non connecté'], 401);
        }

        /** @var Employe $medecin */
        $medecin = $this->em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        if (!$medecin) {
            return $this->json(['error' => 'Aucun médecin trouvé'], 404);
        }

        $fromStr = $request->query->get('from');
        $toStr   = $request->query->get('to');

        $from = $fromStr ? new \DateTimeImmutable($fromStr . ' 00:00:00') : (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        $to   = $toStr   ? new \DateTimeImmutable($toStr   . ' 23:59:59') : (new \DateTimeImmutable())->setTime(23, 59, 59);

        $allStats  = $this->reportService->periodicDoctorReports($from, $to);
        $doctorData = null;
        foreach ($allStats['doctors'] as $doc) {
            if ((string) $doc['id'] === (string) $medecin->getId()) {
                $doctorData = $doc;
                break;
            }
        }

        $totalConsultations = $doctorData['consultations'] ?? 0;
        $paidConsultations  = $doctorData['consultations_paid'] ?? 0;

        return $this->json([
            'fullName'      => $medecin->getFullName(),
            'nom'           => $medecin->getNom(),
            'prenom'        => $medecin->getPrenom(),
            'matricule'     => $medecin->getMatricule(),
            'fonction'      => $medecin->getFonction(),
            'telephone'     => $medecin->getTelephone(),
            'email'         => $medecin->getEmail(),
            'type'          => $medecin->getType(),
            'typeSalaire'   => $medecin->getTypeSalaire(),
            'valeurSalaire' => $medecin->getValeurSalaire(),
            'typeContrat'   => $medecin->getTypeContrat(),
            'dureeContrat'  => $medecin->getDureeContrat(),
            'joursTravailles' => $medecin->getComingDaysInWeek(),
            'dateEmbauche'  => $medecin->getDateEmbauche()?->format('Y-m-d'),
            'stats' => [
                'patientsTotal'           => ($doctorData['new_patients'] ?? 0) + ($doctorData['returning_patients'] ?? 0),
                'totalConsultations'      => $totalConsultations,
                'consultationsEnAttente'  => 0,
                'rdvJour'                 => 0,
            ],
            'period' => [
                'freeConsultations' => max(0, $totalConsultations - $paidConsultations),
                'paidConsultations' => $paidConsultations,
                'rdvPlanifies'      => 0,
                'rdvEnAttente'      => 0,
                'rdvValides'        => 0,
                'rdvReportes'       => 0,
                'rdvAnnules'        => 0,
                'apportTotal'       => $doctorData['apport'] ?? 0,
                'apportConsultations' => $doctorData['apport_consultations'] ?? 0,
                'apportActes'       => $doctorData['apport_actes'] ?? 0,
                'apportPatient'     => $doctorData['apport_patient'] ?? 0,
                'apportAssurance'   => $doctorData['apport_assurance'] ?? 0,
                'revenue'           => $doctorData['revenue'] ?? 0,
                'revenueConsultations' => $doctorData['revenue_consultations'] ?? 0,
                'revenueActes'      => $doctorData['revenue_actes'] ?? 0,
                'revenueReliquats'  => $doctorData['revenue_reliquats'] ?? 0,
                'revenueAssurance'  => $doctorData['revenue_assurance'] ?? 0,
                'revenueCash'       => $doctorData['revenue_cash'] ?? 0,
                'revenueTotal'      => $doctorData['revenue_total'] ?? 0,
                'reliquat'          => $doctorData['reliquat'] ?? 0,
                'paiementsReliquats' => $doctorData['paiements_reliquats'] ?? [],
                'paiementsReliquatsTotal' => $doctorData['paiements_reliquats_total'] ?? 0,
                'actesMedicaux'     => $doctorData['actes'] ?? [],
            ],
        ]);
    }
}