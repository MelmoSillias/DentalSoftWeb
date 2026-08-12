<?php

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\QueryBus;

final class SyncQueryBus implements QueryBus
{
    public function __construct(private readonly HandlerLocator $queryHandlerLocator)
    {
    }

    public function ask(object $query): mixed
    {
        $handler = $this->queryHandlerLocator->get($query);

        return $handler($query);
    }
}
