<?php

namespace App\Scheduling\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class HolidayController extends AbstractController
{
    private string $configPath;

    public function __construct(\Symfony\Component\HttpKernel\KernelInterface $kernel)
    {
        $this->configPath = $kernel->getProjectDir() . '/config/jours_feries.json';
    }

    #[Route('/api/holidays', name: 'api_jours_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = json_decode(file_get_contents($this->configPath), true);
        return $this->json($data);
    }

    #[Route('/api/holidays', name: 'api_add_ferie', methods: ['POST'])]
    public function addHoliday(Request $request): JsonResponse
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

    #[Route('/api/holidays/{date}', name: 'api_delete_ferie', methods: ['DELETE'])]
    public function deleteHoliday(string $date): JsonResponse
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Date invalide'], 400);
        }

        $data = json_decode(file_get_contents($this->configPath), true);
        $key = array_search($date, $data['feries']);
        if ($key !== false) {
            unset($data['feries'][$key]);
            $data['feries'] = array_values($data['feries']);
            file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/holidays/closures', name: 'api_update_fermetures', methods: ['PUT'])]
    public function updateClosures(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $closures = $payload['closures'] ?? [];

        $data = json_decode(file_get_contents($this->configPath), true);
        $data['fermetures'] = $closures;
        file_put_contents($this->configPath, json_encode($data, JSON_PRETTY_PRINT));

        return $this->json(['success' => true]);
    }
}