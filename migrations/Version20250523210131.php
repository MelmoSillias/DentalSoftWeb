<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250523210131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE devis CHANGE montant montant DOUBLE PRECISION DEFAULT NULL, CHANGE reste reste DOUBLE PRECISION DEFAULT NULL, CHANGE statut statut INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis CHANGE devis_id devis_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rdv ADD reported_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE devis CHANGE montant montant DOUBLE PRECISION NOT NULL, CHANGE reste reste DOUBLE PRECISION NOT NULL, CHANGE statut statut INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis CHANGE devis_id devis_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rdv DROP reported_at
        SQL);
    }
}
