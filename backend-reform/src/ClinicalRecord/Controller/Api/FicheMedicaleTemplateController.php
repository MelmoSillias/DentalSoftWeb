<?php

namespace App\ClinicalRecord\Controller\Api;

use App\CareDelivery\Service\ConsultationService;
use App\ClinicalRecord\Entity\FormTemplate;
use App\ClinicalRecord\Service\FicheMedicaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiche-medicale', name: 'api_fiche_medicale_template_')]
class FicheMedicaleTemplateController extends AbstractController
{
    public function __construct(
        private FicheMedicaleService $ficheMedicaleService,
        private ConsultationService $consultationService,
        private EntityManagerInterface $em,
    ) {
    }

    private function restrictToConnectedMedecin(): bool
    {
        return $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
    }

    #[Route('/update', methods: ['POST'], name: 'update')]
    public function update(Request $request): JsonResponse
    {
        $raw = $request->request->get('data');
        $payload = is_string($raw) ? json_decode($raw, true) : json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Payload JSON invalide.');
        }

        $ficheId = (int) ($payload['ficheId'] ?? 0);
        if ($ficheId <= 0) {
            throw new BadRequestHttpException('Le champ ficheId est requis.');
        }

        $formTemplateKey = isset($payload['formTemplateKey']) ? (string) $payload['formTemplateKey'] : null;
        $hasTemplatePayload = array_key_exists('formData', $payload) || array_key_exists('formTemplateKey', $payload);
        $formData = $payload['formData'] ?? null;
        if ($formData !== null && !is_array($formData)) {
            throw new BadRequestHttpException('Le champ formData doit etre un objet JSON.');
        }

        $consultationId = (int) ($payload['consultationId'] ?? 0);
        $consultationPayload = $payload['consultation'] ?? null;
        if ($consultationPayload !== null && !is_array($consultationPayload)) {
            throw new BadRequestHttpException('Le champ consultation doit etre un objet JSON.');
        }

        $files = $request->files->get('documentsFiles', []);
        if ($hasTemplatePayload) {
            $updated = $this->ficheMedicaleService->updateFromTemplate(
                $ficheId,
                $formTemplateKey,
                is_array($formData) ? $formData : [],
                is_array($files) ? $files : [],
            );
        } else {
            $updated = $this->ficheMedicaleService->getFicheJson($ficheId);
        }

        if ($consultationId > 0 && is_array($consultationPayload)) {
            $this->consultationService->updateConsultation(
                $ficheId,
                $consultationId,
                $consultationPayload,
                $this->getUser(),
                $this->restrictToConnectedMedecin(),
            );
        }

        return new JsonResponse([
            'success' => true,
            'fiche' => $updated,
        ]);
    }

    #[Route('/templates', methods: ['GET'], name: 'templates')]
    public function listTemplates(): JsonResponse
    {
        $templates = $this->em->getRepository(FormTemplate::class)->findBy([], ['key' => 'ASC', 'version' => 'DESC']);

        $data = array_map(static fn(FormTemplate $template) => [
            'id' => $template->getId(),
            'key' => $template->getKey(),
            'version' => $template->getVersion(),
            'structure' => $template->getStructure(),
        ], $templates);

        return new JsonResponse($data);
    }
}
