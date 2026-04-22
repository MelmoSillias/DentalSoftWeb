<?php

namespace App\Controller\Api\Report;

use App\Entity\Employe;
use App\Service\ReportService;
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

        $stats = $this->reportService->medecinDashboard($medecin, $from, $to);

        return $this->json($stats);
    }
}