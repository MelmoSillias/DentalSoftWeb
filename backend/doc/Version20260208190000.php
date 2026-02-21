<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type field to consultation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP type');
    }
}
