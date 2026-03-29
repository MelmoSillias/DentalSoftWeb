<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les clés de type/famille des modes de paiement et les statuts de validation des transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE mode_de_paiement ADD type_key VARCHAR(50) DEFAULT NULL, ADD family_key VARCHAR(20) DEFAULT 'classic' NOT NULL, ADD coverage_rate DOUBLE PRECISION DEFAULT NULL");
        $this->addSql("UPDATE mode_de_paiement SET type_key = CASE
            WHEN LOWER(REPLACE(REPLACE(type, ' ', '_'), '-', '_')) LIKE '%mobile%money%' THEN 'mobile_money'
            WHEN LOWER(type) LIKE '%assur%' THEN 'insurance'
            WHEN LOWER(type) LIKE '%vir%' THEN 'bank_transfer'
            WHEN LOWER(type) LIKE '%che%' THEN 'cheque'
            WHEN LOWER(type) LIKE '%esp%' OR LOWER(type) LIKE '%cash%' THEN 'cash'
            ELSE 'other'
        END");
        $this->addSql("UPDATE mode_de_paiement SET family_key = CASE WHEN type_key = 'insurance' THEN 'insurance' ELSE 'classic' END");
        $this->addSql("UPDATE mode_de_paiement SET coverage_rate = NULL WHERE family_key <> 'insurance'");

        $this->addSql("ALTER TABLE `transaction`
            ADD validated TINYINT(1) DEFAULT 0 NOT NULL,
            ADD validation_status VARCHAR(20) DEFAULT 'pending' NOT NULL,
            ADD validation_comment LONGTEXT DEFAULT NULL,
            ADD validated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ADD rejected_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            ADD role_paiement VARCHAR(32) DEFAULT 'direct' NOT NULL,
            ADD taux_prise_en_charge DOUBLE PRECISION DEFAULT NULL,
            ADD devis_id INT DEFAULT NULL,
            ADD consultation_id INT DEFAULT NULL");
        $this->addSql('CREATE INDEX IDX_TRANSACTION_DEVIS ON `transaction` (devis_id)');
        $this->addSql('CREATE INDEX IDX_TRANSACTION_CONSULTATION ON `transaction` (consultation_id)');
        $this->addSql('ALTER TABLE `transaction` ADD CONSTRAINT FK_TRANSACTION_DEVIS FOREIGN KEY (devis_id) REFERENCES devis (id)');
        $this->addSql('ALTER TABLE `transaction` ADD CONSTRAINT FK_TRANSACTION_CONSULTATION FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql("UPDATE `transaction` t
            INNER JOIN mode_de_paiement mdp ON mdp.id = t.mode_de_paiement_id
            SET
                t.validated = CASE WHEN COALESCE(mdp.type_key, '') IN ('cash', 'mobile_money') THEN 1 ELSE 0 END,
                t.validation_status = CASE WHEN COALESCE(mdp.type_key, '') IN ('cash', 'mobile_money') THEN 'validated' ELSE 'pending' END,
                t.validated_at = CASE WHEN COALESCE(mdp.type_key, '') IN ('cash', 'mobile_money') THEN NOW() ELSE NULL END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `transaction` DROP FOREIGN KEY FK_TRANSACTION_DEVIS');
        $this->addSql('ALTER TABLE `transaction` DROP FOREIGN KEY FK_TRANSACTION_CONSULTATION');
        $this->addSql('DROP INDEX IDX_TRANSACTION_DEVIS ON `transaction`');
        $this->addSql('DROP INDEX IDX_TRANSACTION_CONSULTATION ON `transaction`');
        $this->addSql('ALTER TABLE `transaction` DROP devis_id, DROP consultation_id, DROP validated, DROP validation_status, DROP validation_comment, DROP validated_at, DROP rejected_at, DROP role_paiement, DROP taux_prise_en_charge');
        $this->addSql('ALTER TABLE mode_de_paiement DROP type_key, DROP family_key, DROP coverage_rate');
    }
}