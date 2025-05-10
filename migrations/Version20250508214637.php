<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250508214637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD fiche_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD CONSTRAINT FK_D3B4A186DF522508 FOREIGN KEY (fiche_id) REFERENCES fiche_observation (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3B4A186DF522508 ON document_medical (fiche_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical DROP FOREIGN KEY FK_D3B4A186DF522508
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_D3B4A186DF522508 ON document_medical
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical DROP fiche_id
        SQL);
    }
}
