<?php

namespace App\IdentityAccess\Controller\Api;

use App\IdentityAccess\Application\Query\ListInfirmiers\ListInfirmiersQuery;
use App\IdentityAccess\Application\Query\ListMedecins\ListMedecinsQuery;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MedecinController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getAllMedecins(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListMedecinsQuery()));
    }

    #[Route('/api/infirmiers', name: 'api_infirmiers', methods: ['GET'])]
    public function getAllInfirmiers(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListInfirmiersQuery()));
    }
}
