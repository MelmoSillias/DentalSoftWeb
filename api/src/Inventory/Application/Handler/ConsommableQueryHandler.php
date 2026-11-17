<?php

namespace App\Inventory\Application\Handler;

use App\Inventory\Application\Mapper\ConsommableResponseMapper;
use App\Inventory\Application\Query\FetchStocksQuery;
use App\Inventory\Domain\Entity\Consommable;

use App\Inventory\Domain\Repository\ConsommableRepositoryInterface;
use App\Inventory\Domain\Repository\StockRepositoryInterface;
use DateTime;

final class ConsommableQueryHandler
{
    public function __construct(
        private ConsommableRepositoryInterface $consRepo,
        private StockRepositoryInterface $stockRepo,
        private ConsommableResponseMapper $mapper,
    ) {
    }

    public function listWithVariations(): array
    {
        return [
            'consommables' => $this->consRepo->findAll(),
            'variations' => $this->stockRepo->findBy([], ['datePrise' => 'DESC']),
        ];
    }

    public function details(Consommable $consommable): array
    {
        return $this->mapper->details($consommable);
    }

    public function fetchConsommables(): array
    {
        return array_map($this->mapper->summary(...), $this->consRepo->findAll());
    }

    public function fetchStocks(FetchStocksQuery $query): array
    {
        $start = (new DateTime($query->startDate ?? '2 year ago'))->setTime(0, 0);
        $end = (new DateTime($query->endDate ?? 'today'))->setTime(23, 59);

        $data = [];
        foreach ($this->stockRepo->findHistory($start, $end, $query->consumableId) as $stock) {
            $data[] = $this->mapper->stock($stock);
        }

        return $data;
    }
}
