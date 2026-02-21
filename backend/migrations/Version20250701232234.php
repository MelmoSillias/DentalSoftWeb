<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250701232234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis ADD consultation_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis ADD CONSTRAINT FK_23BF203462FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_23BF203462FF6CDF ON paiement_devis (consultation_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction ADD paiement_devis_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction ADD CONSTRAINT FK_723705D18B7902E0 FOREIGN KEY (paiement_devis_id) REFERENCES paiement_devis (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_723705D18B7902E0 ON transaction (paiement_devis_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis DROP FOREIGN KEY FK_23BF203462FF6CDF
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_23BF203462FF6CDF ON paiement_devis
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE paiement_devis DROP consultation_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction DROP FOREIGN KEY FK_723705D18B7902E0
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_723705D18B7902E0 ON transaction
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transaction DROP paiement_devis_id
        SQL);
    }
}
