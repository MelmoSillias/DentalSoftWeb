<?php

namespace App\Communication\Application\Command\SaveSmsTemplates;

final class SaveSmsTemplatesCommand
{
    /**
     * @param list<array<string, mixed>> $templates
     */
    public function __construct(public readonly array $templates)
    {
    }
}
