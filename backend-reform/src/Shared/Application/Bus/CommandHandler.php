<?php

namespace App\Shared\Application\Bus;

/**
 * Marker interface for command handlers.
 * Implementations must expose __invoke(Command $command): mixed
 * with a single typed object parameter matching the command class.
 */
interface CommandHandler
{
}
