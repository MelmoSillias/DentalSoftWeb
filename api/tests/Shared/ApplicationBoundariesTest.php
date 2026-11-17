<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

final class ApplicationBoundariesTest extends TestCase
{
    private const CONTEXTS = ['Patient', 'CareDelivery', 'Billing', 'Inventory'];
    private const FACADES = ['PatientService', 'ConsultationService', 'FinanceService', 'ConsommableService'];

    public function testResponseMappersDoNotLoadDataFromPersistence(): void
    {
        foreach ($this->applicationFiles(['Mapper']) as $file) {
            $source = file_get_contents($file);

            self::assertDoesNotMatchRegularExpression(
                '/\b(?:use|new)\s+\\\\?(?:Doctrine\\\\|App\\\\[^;\s]*(?:\\\\Infrastructure\\\\|\\\\Repository\\\\))/',
                $source,
                $file . ' must project supplied data, not load it from persistence.',
            );
        }
    }

    public function testExtractedComponentsDoNotDependBackOnCompatibilityFacades(): void
    {
        foreach ($this->applicationFiles(['Handler', 'Mapper', 'Policy', 'ReadModel', 'UseCase']) as $file) {
            $source = file_get_contents($file);

            foreach (self::FACADES as $facade) {
                self::assertDoesNotMatchRegularExpression(
                    '/\b' . $facade . '\b/',
                    $source,
                    $file . ' must not depend back on ' . $facade . '.',
                );
            }
        }
    }

    public function testQueryObjectsOnlyCarryRequestData(): void
    {
        foreach ($this->applicationFiles(['Query']) as $file) {
            $source = file_get_contents($file);

            self::assertDoesNotMatchRegularExpression(
                '/\buse\s+(?:Doctrine\\\\|Symfony\\\\|App\\\\[^;\s]*\\\\(?:Infrastructure|Service|Handler|UseCase)\\\\)/',
                $source,
                $file . ' must not depend on executable application or infrastructure services.',
            );
        }
    }

    /** @return list<string> */
    private function applicationFiles(array $sections): array
    {
        $files = [];
        foreach (self::CONTEXTS as $context) {
            foreach ($sections as $section) {
                $files = array_merge($files, glob(dirname(__DIR__, 2) . '/src/' . $context . '/Application/' . $section . '/*.php'));
            }
        }

        self::assertNotEmpty($files, 'Architecture checks must inspect existing application files.');

        return $files;
    }
}
