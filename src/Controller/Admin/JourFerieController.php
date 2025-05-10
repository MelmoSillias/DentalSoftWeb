<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/jours')]
class JourFerieController extends AbstractController
{
    private string $configPath;

    public function __construct(\Symfony\Component\HttpKernel\KernelInterface $kernel)
    {
        $this->configPath = $kernel->getProjectDir() . '/config/jours_feries.json';
    }

    #[Route('/list', name: 'api_jours_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = json_decode(file_get_contents($this->configPath), true);
        return $this->json($data);
    }

    #[Route('/ferie/add', name: 'api_add_ferie', methods: ['POST'])]
    public function addFerie(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $date = $payload['date'] ?? null;

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Date invalide'], 400);
        }

        $data = json_decode(file_get_contents($this->configPath), true);
        if (!in_array($date, $data['feries'])) {
            $data['feries'][] = $date;
            file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
        }

        return $this->json(['success' => true]);
    }

    #[Route('/ferie/delete', name: 'api_delete_ferie', methods: ['POST'])]
    public function deleteFerie(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $date = $payload['date'] ?? null;

        $data = json_decode(file_get_contents($this->configPath), true);
        $data['feries'] = array_values(array_filter($data['feries'], fn($d) => $d !== $date));
        file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));

        return $this->json(['success' => true]);
    }

    #[Route('/fermetures/update', name: 'api_update_fermetures', methods: ['POST'])]
    public function updateFermetures(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $jours = $payload['jours'] ?? [];

        if (!is_array($jours)) {
            return $this->json(['error' => 'Format invalide'], 400);
        }

        $data = json_decode(file_get_contents($this->configPath), true);
        $data['fermeturesHebdo'] = array_map('intval', $jours);
        file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));

        return $this->json(['success' => true]);
    }
}
