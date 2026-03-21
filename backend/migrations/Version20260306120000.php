<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout module SMS Orange: config, templates, logs, queue et préférences patient.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE patient
                ADD sms_patient_created TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_receipt TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_ticket TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_invoice TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_appointment_reminder TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_unsubscribed TINYINT(1) DEFAULT 0 NOT NULL,
                ADD sms_blacklisted TINYINT(1) DEFAULT 0 NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sms_provider_config (
                id INT AUTO_INCREMENT NOT NULL,
                provider VARCHAR(64) NOT NULL,
                enabled TINYINT(1) NOT NULL,
                client_id VARCHAR(255) DEFAULT NULL,
                client_secret_encrypted LONGTEXT DEFAULT NULL,
                sender_name VARCHAR(255) DEFAULT NULL,
                base_url VARCHAR(255) NOT NULL,
                oauth_url VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sms_template (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(80) NOT NULL,
                name VARCHAR(120) NOT NULL,
                type VARCHAR(80) NOT NULL,
                content LONGTEXT NOT NULL,
                enabled TINYINT(1) NOT NULL,
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_A760D5A877153098 (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sms_log (
                id INT AUTO_INCREMENT NOT NULL,
                patient_id INT DEFAULT NULL,
                phone VARCHAR(64) NOT NULL,
                message LONGTEXT NOT NULL,
                status VARCHAR(24) NOT NULL,
                type VARCHAR(64) NOT NULL,
                source VARCHAR(64) NOT NULL,
                provider VARCHAR(32) NOT NULL,
                provider_message_id VARCHAR(255) DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_sms_log_status (status),
                INDEX idx_sms_log_type (type),
                INDEX idx_sms_log_created_at (created_at),
                INDEX IDX_C7A2A7396B899279 (patient_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sms_queue (
                id INT AUTO_INCREMENT NOT NULL,
                patient_id INT DEFAULT NULL,
                phone VARCHAR(64) NOT NULL,
                message LONGTEXT NOT NULL,
                status VARCHAR(24) NOT NULL,
                retry_count INT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                send_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                last_error LONGTEXT DEFAULT NULL,
                type VARCHAR(64) NOT NULL,
                source VARCHAR(64) NOT NULL,
                metadata JSON DEFAULT NULL,
                INDEX idx_sms_queue_status (status),
                INDEX idx_sms_queue_created_at (created_at),
                INDEX IDX_20DBA6A96B899279 (patient_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE sms_log ADD CONSTRAINT FK_C7A2A7396B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sms_queue ADD CONSTRAINT FK_20DBA6A96B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sms_log DROP FOREIGN KEY FK_C7A2A7396B899279');
        $this->addSql('ALTER TABLE sms_queue DROP FOREIGN KEY FK_20DBA6A96B899279');

        $this->addSql('DROP TABLE sms_provider_config');
        $this->addSql('DROP TABLE sms_template');
        $this->addSql('DROP TABLE sms_log');
        $this->addSql('DROP TABLE sms_queue');

        $this->addSql(<<<'SQL'
            ALTER TABLE patient
                DROP sms_patient_created,
                DROP sms_receipt,
                DROP sms_ticket,
                DROP sms_invoice,
                DROP sms_appointment_reminder,
                DROP sms_unsubscribed,
                DROP sms_blacklisted
        SQL);
    }
}
