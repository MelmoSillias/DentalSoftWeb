<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attribution column to acte_medical for cabinet vs medecin services';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE acte_medical ADD attribution VARCHAR(20) DEFAULT 'medecin' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acte_medical DROP attribution');
    }
}
