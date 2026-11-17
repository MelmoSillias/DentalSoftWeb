<?php

namespace App\Patient\Application\Handler;

use App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use App\Patient\Application\Query\PatientCollectionQuery;
use App\Patient\Application\ReadModel\PatientSummaryProvider;
use App\Patient\Application\Policy\PatientReadScopePolicy;

final class PatientCollectionHandler
{
    public function __construct(
        private PatientRepository $patientRepo,
        private PatientSummaryProvider $summaryProvider,
        private PatientReadScopePolicy $readScopePolicy,
        private CacheInterface $cache,
    ) {
    }

    public function handle(PatientCollectionQuery $request): array
    {
        $user = $request->user;
        $medecinOnly = $request->medecinOnly;
        $paginated = $request->paginated;
        $page = $request->page;
        $limit = $request->limit;
        $query = $request->query;
        $sortField = $request->sortField;
        $sortOrder = $request->sortOrder;
        $resolvedPage = max(1, $page);
        $resolvedLimit = max(1, min($limit, 100));
        $resolvedSortOrder = strtolower($sortOrder ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $medecinOnly = $this->readScopePolicy->restrictToMedecin($medecinOnly);

        $medecin = null;
        if ($medecinOnly) {
            $resolved = $this->readScopePolicy->resolveMedecinFromUser($user);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $medecin = $resolved['employe'];
        }

        if (!$paginated) {
            $patients = $medecinOnly
                ? $this->patientRepo->findPatientsByMedecin($medecin, $query, $sortField, $resolvedSortOrder)
                : $this->patientRepo->findBy(['deletedAt' => null], ['nom' => 'ASC']);

            return array_values($this->summaryProvider->getSummaries($patients));
        }

        $cacheScope = $medecinOnly ? sprintf('medecin.%d', $medecin->getId()) : 'global';
        $cacheKey = sprintf(
            'patients.collection.%s.%d.%d.%s.%s.%s',
            $cacheScope,
            $resolvedPage,
            $resolvedLimit,
            sha1((string) $query),
            $sortField ?? 'default',
            $resolvedSortOrder
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($medecinOnly, $medecin, $resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder) {
            $item->expiresAfter(60);

            $result = $medecinOnly
                ? $this->patientRepo->paginatePatientsByMedecin($medecin, $resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder)
                : $this->patientRepo->paginatePatients($resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder);

            $items = $this->summaryProvider->getSummaries($result['items']);

            return [
                'items' => $items,
                'total' => $result['total'],
                'page' => $resolvedPage,
                'limit' => $resolvedLimit,
                'sortField' => $sortField,
                'sortOrder' => $resolvedSortOrder,
            ];
        });
    }

    public function listDeletedPatientsPaginated(int $page, int $limit, ?string $query = null): array
    {
        $resolvedPage = max(1, $page);
        $resolvedLimit = max(1, min($limit, 100));

        $result = $this->patientRepo->paginateDeletedPatients($resolvedPage, $resolvedLimit, $query);
        $summaries = $this->summaryProvider->getSummaries($result['items']);
        $items = [];
        foreach ($summaries as $index => $summary) {
            $patient = $result['items'][$index] ?? null;
            $items[] = [
                ...$summary,
                'deletedAt' => $patient?->getDeletedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'items' => $items,
            'total' => $result['total'],
            'page' => $resolvedPage,
            'limit' => $resolvedLimit,
        ];
    }
}
