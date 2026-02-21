<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Placeholder migration to keep Version20250701222528 registered.
 */
final class Version20250701222528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Existing migration already applied in production; kept for history alignment.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank. Schema already up to date for this version.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank. Nothing to revert for this placeholder.
    }
}
