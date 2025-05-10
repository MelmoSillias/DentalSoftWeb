<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250509113202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical DROP FOREIGN KEY FK_D3B4A18662FF6CDF
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_D3B4A18662FF6CDF ON document_medical
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical DROP consultation_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD consultation_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_medical ADD CONSTRAINT FK_D3B4A18662FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D3B4A18662FF6CDF ON document_medical (consultation_id)
        SQL);
    }
}
