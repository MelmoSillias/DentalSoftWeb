<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Doctrine\GuardedSql;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012223000 extends AbstractMigration
{
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Add soft-delete timestamp on employe';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE employe ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE employe DROP deleted_at');
    }
}
