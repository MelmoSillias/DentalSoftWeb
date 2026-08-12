<?php

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBus;

final class SyncCommandBus implements CommandBus
{
    public function __construct(private readonly HandlerLocator $commandHandlerLocator)
    {
    }

    public function dispatch(object $command): mixed
    {
        $handler = $this->commandHandlerLocator->get($command);

        return $handler($command);
    }
}
