<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add facture_assurance_id to paiement and migrate existing insurance payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement ADD facture_assurance_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E5765EBCC FOREIGN KEY (facture_assurance_id) REFERENCES facture_assurance (id)');
        $this->addSql('CREATE INDEX IDX_B1DC7A1E5765EBCC ON paiement (facture_assurance_id)');

        // Migrate existing insurance payments: link to facture_assurance and unlink from facture/consultation
        $this->addSql(<<<'SQL'
            UPDATE paiement p
            INNER JOIN transaction t ON t.paiement_id = p.id
            INNER JOIN consultation c ON p.consultation_id = c.id OR (p.facture_id IS NOT NULL AND c.id = (SELECT f.consultation_id FROM facture f WHERE f.id = p.facture_id))
            INNER JOIN facture_assurance fa ON fa.consultation_id = c.id
            SET p.facture_assurance_id = fa.id,
                p.facture_id = NULL,
                p.consultation_id = NULL
            WHERE t.role_paiement = 'patient_insurance'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E5765EBCC');
        $this->addSql('DROP INDEX IDX_B1DC7A1E5765EBCC ON paiement');
        $this->addSql('ALTER TABLE paiement DROP facture_assurance_id');
    }
}
