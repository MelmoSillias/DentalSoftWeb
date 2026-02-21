<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208184938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['notification'])) {
            $fks = $sm->listTableForeignKeys('notification');
            foreach ($fks as $fk) {
                $columns = $fk->getLocalColumns();
                if (in_array('consultation_id', $columns, true) || in_array('patient_id', $columns, true)) {
                    $this->addSql(sprintf('ALTER TABLE notification DROP FOREIGN KEY %s', $fk->getName()));
                }
            }
        }
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_BF5476CA62FF6CDF ON notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_BF5476CA6B899279 ON notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_NOTIFICATION_CREATED_AT ON notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_notification_state ON notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_notification_date ON notification
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD type VARCHAR(255) NOT NULL, ADD etat_vu VARCHAR(255) NOT NULL, DROP patient_id, DROP consultation_id, DROP status, CHANGE link link VARCHAR(255) DEFAULT NULL, CHANGE created_at date_envoi DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_state ON notification (etat_vu)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_date ON notification (date_envoi)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX idx_notification_date ON notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_notification_state ON notification
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD patient_id INT DEFAULT NULL, ADD consultation_id INT DEFAULT NULL, ADD status VARCHAR(20) DEFAULT 'non_lu' NOT NULL, DROP type, DROP etat_vu, CHANGE link link VARCHAR(512) DEFAULT NULL, CHANGE date_envoi created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_CONSULTATION FOREIGN KEY (consultation_id) REFERENCES consultation (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CA62FF6CDF ON notification (consultation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CA6B899279 ON notification (patient_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_NOTIFICATION_CREATED_AT ON notification (created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_date ON notification (created_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_notification_state ON notification (status)
        SQL);
    }
}
