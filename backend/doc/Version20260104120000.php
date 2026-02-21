<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notification schema upgrade (priority, status, relations, cleanup helpers).
 */
final class Version20260104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refine notification schema with enums, timestamps, context relations, and cleanup support.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['notification'])) {
            return;
        }

        $columns = $sm->listTableColumns('notification');
        $indexes = $sm->listTableIndexes('notification');
        $fks = $sm->listTableForeignKeys('notification');

        if (isset($columns['message'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE message message LONGTEXT NOT NULL
            SQL);
        }

        if (isset($columns['type']) && !isset($columns['priority'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE type priority VARCHAR(20) NOT NULL DEFAULT 'info'
            SQL);
        }

        if (isset($columns['date_envoi']) && !isset($columns['created_at'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE date_envoi created_at DATETIME NOT NULL
            SQL);
        }

        if (isset($columns['etat_vu']) && !isset($columns['status'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE etat_vu status VARCHAR(20) NOT NULL DEFAULT 'non_lu'
            SQL);
        }

        if (isset($columns['link'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE link link VARCHAR(512) DEFAULT NULL
            SQL);
        }

        $columns = $sm->listTableColumns('notification');

        if (!isset($columns['emitter_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD emitter_id INT DEFAULT NULL
            SQL);
        }

        if (!isset($columns['patient_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD patient_id INT DEFAULT NULL
            SQL);
        }

        if (!isset($columns['consultation_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD consultation_id INT DEFAULT NULL
            SQL);
        }

        // user_id doit rester nullable si la FK utilise SET NULL

        $indexes = $sm->listTableIndexes('notification');
        if (!isset($indexes['idx_notification_created_at'])) {
            $this->addSql(<<<'SQL'
                CREATE INDEX IDX_NOTIFICATION_CREATED_AT ON notification (created_at)
            SQL);
        }

        $fks = $sm->listTableForeignKeys('notification');
        $hasEmitterFk = false;
        $hasPatientFk = false;
        $hasConsultationFk = false;
        foreach ($fks as $fk) {
            $name = $fk->getName();
            if ($name === 'FK_NOTIFICATION_EMITTER') {
                $hasEmitterFk = true;
            }
            if ($name === 'FK_NOTIFICATION_PATIENT') {
                $hasPatientFk = true;
            }
            if ($name === 'FK_NOTIFICATION_CONSULTATION') {
                $hasConsultationFk = true;
            }
        }

        if (!$hasEmitterFk) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_EMITTER FOREIGN KEY (emitter_id) REFERENCES `user` (id)
            SQL);
        }

        if (!$hasPatientFk) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id)
            SQL);
        }

        if (!$hasConsultationFk) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_CONSULTATION FOREIGN KEY (consultation_id) REFERENCES consultation (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['notification'])) {
            return;
        }

        $columns = $sm->listTableColumns('notification');
        $indexes = $sm->listTableIndexes('notification');
        $fks = $sm->listTableForeignKeys('notification');

        foreach ($fks as $fk) {
            if ($fk->getName() === 'FK_NOTIFICATION_EMITTER') {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_EMITTER
                SQL);
                break;
            }
        }
        foreach ($fks as $fk) {
            if ($fk->getName() === 'FK_NOTIFICATION_PATIENT') {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_PATIENT
                SQL);
                break;
            }
        }
        foreach ($fks as $fk) {
            if ($fk->getName() === 'FK_NOTIFICATION_CONSULTATION') {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_CONSULTATION
                SQL);
                break;
            }
        }

        if (isset($indexes['idx_notification_created_at'])) {
            $this->addSql(<<<'SQL'
                DROP INDEX IDX_NOTIFICATION_CREATED_AT ON notification
            SQL);
        }

        if (isset($columns['priority']) && !isset($columns['type'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE priority type VARCHAR(255) NOT NULL
            SQL);
        }

        if (isset($columns['created_at']) && !isset($columns['date_envoi'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE created_at date_envoi DATETIME NOT NULL
            SQL);
        }

        if (isset($columns['status']) && !isset($columns['etat_vu'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE status etat_vu VARCHAR(255) NOT NULL
            SQL);
        }

        if (isset($columns['message'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE message message VARCHAR(255) NOT NULL
            SQL);
        }

        if (isset($columns['link'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification CHANGE link link VARCHAR(255) DEFAULT NULL
            SQL);
        }

        $columns = $sm->listTableColumns('notification');

        if (isset($columns['emitter_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP emitter_id
            SQL);
        }

        if (isset($columns['patient_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP patient_id
            SQL);
        }

        if (isset($columns['consultation_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP consultation_id
            SQL);
        }

        if (isset($columns['user_id'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification MODIFY user_id INT DEFAULT NULL
            SQL);
        }
    }
}
