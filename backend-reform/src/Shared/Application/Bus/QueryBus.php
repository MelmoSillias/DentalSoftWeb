<?php

namespace App\Shared\Application\Bus;

interface QueryBus
{
    public function ask(object $query): mixed;
}
