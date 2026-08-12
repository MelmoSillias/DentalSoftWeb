<?php

namespace App\Shared\Application\Bus;

interface CommandBus
{
    public function dispatch(object $command): mixed;
}
