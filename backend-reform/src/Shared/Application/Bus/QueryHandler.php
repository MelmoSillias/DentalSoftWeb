<?php

namespace App\Shared\Application\Bus;

/**
 * Marker interface for query handlers.
 * Implementations must expose __invoke(Query $query): mixed
 * with a single typed object parameter matching the query class.
 */
interface QueryHandler
{
}
