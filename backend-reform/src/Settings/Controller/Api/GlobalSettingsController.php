<?php

namespace App\Settings\Controller\Api;

use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Service\UserDeviceService;
use App\Patient\Service\PatientService;
use App\Settings\Service\DatabaseMaintenanceService;
use App\Settings\Service\GlobalSettingsService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings', name: 'api_settings_')]
class GlobalSettingsController extends AbstractController
{
    public function __construct(
        private GlobalSettingsService $globalSettingsService,
        private DatabaseMaintenanceService $databaseMaintenanceService,
        private PatientService $patientService,
        private UserDeviceService $userDeviceService,
    ) {
    }

    private function safeErrorMessage(\Throwable $e): string
    {
        $message = (string) $e->getMessage();
        if ($message === '' || mb_check_encoding($message, 'UTF-8')) {
            return $message;
        }

        $converted = @mb_convert_encoding($message, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, CP850');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        $fallback = @iconv('Windows-1252', 'UTF-8//IGNORE', $message);

        return is_string($fallback) && $fallback !== '' ? $fallback : 'Erreur interne (message non UTF-8).';
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
        $user = $this->getUser();
        $roles = $user instanceof User ? $user->getRoles() : [];

        return $this->json($this->globalSettingsService->getPublicGeneralSettingsForRoles($roles));
    }

    #[Route('/general', name: 'general_save', methods: ['PUT'])]
    public function saveGeneralSettings(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true) ?? [];
        $saved = $this->globalSettingsService->saveGeneralSettings($payload);

        return $this->json($saved);
    }

    #[Route('/devices', name: 'devices_list', methods: ['GET'])]
    public function listDevices(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json($this->userDeviceService->listGlobalDevices(50));
    }

    #[Route('/devices/{deviceId}/approve', name: 'devices_approve', methods: ['POST'])]
    public function approveDevice(int $deviceId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $admin = $this->getUser();
        $result = $this->userDeviceService->approveDevice(
            $deviceId,
            $admin instanceof User ? $admin : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/devices/{deviceId}/reject', name: 'devices_reject', methods: ['POST'])]
    public function rejectDevice(int $deviceId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $admin = $this->getUser();
        $result = $this->userDeviceService->rejectDevice(
            $deviceId,
            $admin instanceof User ? $admin : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/devices/{deviceId}/rename', name: 'devices_rename', methods: ['PUT', 'PATCH'])]
    public function renameDevice(int $deviceId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = json_decode($request->getContent(), true) ?? [];
        $result = $this->userDeviceService->renameDevice(
            $deviceId,
            (string) ($payload['name'] ?? ''),
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/devices/{deviceId}', name: 'devices_delete', methods: ['DELETE'])]
    public function deleteDevice(int $deviceId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->userDeviceService->deleteDevice($deviceId);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return $this->json($result, $status);
    }

    #[Route('/general/patient-portal/create-missing', name: 'general_patient_portal_create_missing', methods: ['POST'])]
    public function createMissingPatientPortalAccounts(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->patientService->createMissingPatientPortalAccounts();

        return $this->json($result, $result['status'] ?? 200);
    }

    #[Route('/test-mode/status', name: 'test_mode_status', methods: ['GET'])]
    public function getTestModeStatus(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->json($this->globalSettingsService->getTestModeStatus());
    }

    #[Route('/test-mode/toggle', name: 'test_mode_toggle', methods: ['PUT'])]
    public function toggleTestMode(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $enabled = (bool) ($payload['enabled'] ?? false);
        $password = (string) ($payload['password'] ?? '');
        $deleteTestData = (bool) ($payload['deleteTestData'] ?? true);

        try {
            $result = $this->globalSettingsService->toggleTestMode($enabled, $admin, $password, $deleteTestData);
            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => $this->safeErrorMessage($e)], 500);
        }
    }

    #[Route('/test-mode/clean', name: 'test_mode_clean', methods: ['POST'])]
    public function cleanTestMode(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($payload['password'] ?? '');

        try {
            $result = $this->globalSettingsService->cleanTestModeData($admin, $password);
            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => $this->safeErrorMessage($e)], 500);
        }
    }

    #[Route('/database/export', name: 'database_export', methods: ['POST'])]
    public function exportDatabase(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($payload['password'] ?? '');
        $formats = is_array($payload['formats'] ?? null) ? $payload['formats'] : ['sql'];

        if (!$this->databaseMaintenanceService->verifyAdminPassword($admin, $password)) {
            return $this->json(['error' => 'Mot de passe admin invalide.'], 400);
        }

        try {
            $result = $this->databaseMaintenanceService->createBackup($formats, 'manual_backup');

            $downloadUrls = [];
            foreach (['relativeSqlPath', 'relativeZipPath', 'relativeJsonPath'] as $field) {
                $path = $result[$field] ?? null;
                if (is_string($path) && $path !== '') {
                    $downloadUrls[$field] = '/api/settings/database/export/download?file=' . rawurlencode($path);
                }
            }

            return $this->json([
                'success' => true,
                'message' => 'Sauvegarde/export créé avec succès.',
                'files' => $result,
                'downloadUrls' => $downloadUrls,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $this->safeErrorMessage($e)], 500);
        }
    }

    #[Route('/database/export/download', name: 'database_export_download', methods: ['GET'])]
    public function downloadDatabaseExport(Request $request): BinaryFileResponse|JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = trim((string) $request->query->get('file', ''));
        if ($file === '') {
            return $this->json(['error' => 'Paramètre file manquant.'], 400);
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $absolute = rtrim((string) $projectDir, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), '/\\');
        $realPath = realpath($absolute);

        if (!$realPath || !is_file($realPath)) {
            return $this->json(['error' => 'Fichier export introuvable.'], 404);
        }

        $backupsRoot = realpath(rtrim((string) $projectDir, '/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'backups');
        if (!$backupsRoot || !str_starts_with(str_replace('\\', '/', $realPath), str_replace('\\', '/', $backupsRoot) . '/')) {
            return $this->json(['error' => 'Accès au fichier refusé.'], 403);
        }

        $response = new BinaryFileResponse($realPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($realPath));

        return $response;
    }

    #[Route('/database/reset', name: 'database_reset', methods: ['POST'])]
    public function resetDatabase(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        if ((int) ($admin->getId() ?? 0) !== 1) {
            return $this->json(['error' => 'Action réservée au super-admin (id=1).'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($payload['password'] ?? '');

        if (!$this->databaseMaintenanceService->verifyAdminPassword($admin, $password)) {
            return $this->json(['error' => 'Mot de passe admin invalide.'], 400);
        }

        try {
            $backup = $this->databaseMaintenanceService->createBackup(['sql', 'zip', 'json'], 'pre_reset_backup');
            $result = $this->databaseMaintenanceService->resetDatabaseDataPreservingSuperAdmin();

            return $this->json([
                'success' => true,
                'message' => 'Base réinitialisée. Super-admin id=1 conservé.',
                'backup' => $backup,
                'details' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $this->safeErrorMessage($e)], 500);
        }
    }
}
