<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106101640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['notification'])) {
            return;
        }

        $columns = $sm->listTableColumns('notification');
        $hasNewSchema = isset($columns['created_at'], $columns['priority'], $columns['status'])
            && !isset($columns['type'])
            && !isset($columns['date_envoi'])
            && !isset($columns['etat_vu']);
        if ($hasNewSchema) {
            return;
        }

        if (!isset($columns['type'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD type VARCHAR(255) NOT NULL
            SQL);
        }
        if (!isset($columns['date_envoi'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD date_envoi DATETIME NOT NULL
            SQL);
        }
        if (!isset($columns['etat_vu'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification ADD etat_vu VARCHAR(255) NOT NULL
            SQL);
        }

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
        if (isset($columns['created_at'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP created_at
            SQL);
        }
        if (isset($columns['priority'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP priority
            SQL);
        }
        if (isset($columns['status'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE notification DROP status
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD emitter_id INT DEFAULT NULL, ADD patient_id INT DEFAULT NULL, ADD consultation_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ADD priority VARCHAR(20) DEFAULT 'info' NOT NULL, ADD status VARCHAR(20) DEFAULT 'non_lu' NOT NULL, DROP type, DROP date_envoi, DROP etat_vu, CHANGE message message LONGTEXT NOT NULL, CHANGE link link VARCHAR(512) DEFAULT NULL
        SQL);
    }
}
