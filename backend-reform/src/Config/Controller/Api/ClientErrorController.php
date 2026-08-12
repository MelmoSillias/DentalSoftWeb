<?php

namespace App\Config\Controller\Api;

use App\Config\Service\ClientErrorReportService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientErrorController extends AbstractController
{
    private const MAX_BODY_BYTES = 4096;

    public function __construct(
        private readonly ClientErrorReportService $clientErrorReportService,
    ) {
    }

    #[Route('/api/client-errors', name: 'api_client_errors', methods: ['POST'])]
    public function report(Request $request): Response
    {
        $content = $request->getContent();
        if ($content === '' || \strlen($content) > self::MAX_BODY_BYTES) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if (!\is_array($data)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->clientErrorReportService->report(
            $data,
            $userId,
            $request->getClientIp()
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
