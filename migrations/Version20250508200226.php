<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250508200226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE devis DROP INDEX IDX_8B27C52BDF522508, ADD UNIQUE INDEX UNIQ_8B27C52BDF522508 (fiche_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD description LONGTEXT DEFAULT NULL, DROP validite
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE devis DROP INDEX UNIQ_8B27C52BDF522508, ADD INDEX IDX_8B27C52BDF522508 (fiche_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD validite DATETIME DEFAULT NULL, DROP description
        SQL);
    }
}
