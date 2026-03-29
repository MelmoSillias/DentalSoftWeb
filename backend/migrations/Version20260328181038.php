<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328181038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mode_de_paiement DROP default_insurance_rate');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              paiement_devis
            DROP
              INDEX UNIQ_23BF203462FF6CDF,
            ADD
              INDEX IDX_23BF203462FF6CDF (consultation_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              paiement_devis
            ADD
              groupe_paiement VARCHAR(64) DEFAULT NULL,
            ADD
              role_paiement VARCHAR(32) DEFAULT 'direct' NOT NULL,
            ADD
              taux_prise_en_charge DOUBLE PRECISION DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE sms_log DROP FOREIGN KEY FK_C7A2A7396B899279');
        $this->addSql('DROP INDEX idx_c7a2a7396b899279 ON sms_log');
        $this->addSql('CREATE INDEX IDX_A9E43D706B899279 ON sms_log (patient_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sms_log
            ADD
              CONSTRAINT FK_C7A2A7396B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('ALTER TABLE sms_queue DROP FOREIGN KEY FK_20DBA6A96B899279');
        $this->addSql('DROP INDEX idx_20dba6a96b899279 ON sms_queue');
        $this->addSql('CREATE INDEX IDX_FD4EA63E6B899279 ON sms_queue (patient_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sms_queue
            ADD
              CONSTRAINT FK_20DBA6A96B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('DROP INDEX uniq_a760d5a877153098 ON sms_template');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F1963E8277153098 ON sms_template (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mode_de_paiement ADD default_insurance_rate DOUBLE PRECISION DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              paiement_devis
            DROP
              INDEX IDX_23BF203462FF6CDF,
            ADD
              UNIQUE INDEX UNIQ_23BF203462FF6CDF (consultation_id)
        SQL);
        $this->addSql('ALTER TABLE paiement_devis DROP groupe_paiement, DROP role_paiement, DROP taux_prise_en_charge');
        $this->addSql('ALTER TABLE sms_log DROP FOREIGN KEY FK_A9E43D706B899279');
        $this->addSql('DROP INDEX idx_a9e43d706b899279 ON sms_log');
        $this->addSql('CREATE INDEX IDX_C7A2A7396B899279 ON sms_log (patient_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sms_log
            ADD
              CONSTRAINT FK_A9E43D706B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('ALTER TABLE sms_queue DROP FOREIGN KEY FK_FD4EA63E6B899279');
        $this->addSql('DROP INDEX idx_fd4ea63e6b899279 ON sms_queue');
        $this->addSql('CREATE INDEX IDX_20DBA6A96B899279 ON sms_queue (patient_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              sms_queue
            ADD
              CONSTRAINT FK_FD4EA63E6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('DROP INDEX uniq_f1963e8277153098 ON sms_template');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A760D5A877153098 ON sms_template (code)');
    }
}
