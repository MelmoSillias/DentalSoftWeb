<?php

namespace App\Controller\Api;

use App\Service\GlobalSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings', name: 'api_settings_')]
class GlobalSettingsController extends AbstractController
{
    public function __construct(private GlobalSettingsService $globalSettingsService)
    {
    }

    #[Route('/general', name: 'general_get', methods: ['GET'])]
    public function getGeneralSettings(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json($this->globalSettingsService->getGeneralSettings());
    }

    #[Route('/general/public', name: 'general_public_get', methods: ['GET'])]
    public function getPublicGeneralSettings(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->json($this->globalSettingsService->getPublicGeneralSettings());
    }

    #[Route('/general', name: 'general_save', methods: ['PUT'])]
    public function saveGeneralSettings(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true) ?? [];
        $saved = $this->globalSettingsService->saveGeneralSettings($payload);

        return $this->json($saved);
    }
}
