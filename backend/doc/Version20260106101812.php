<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106101812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['notification'])) {
            $columns = $sm->listTableColumns('notification');
            $hasNewSchema = isset($columns['created_at'], $columns['priority'], $columns['status'])
                && !isset($columns['type'])
                && !isset($columns['date_envoi'])
                && !isset($columns['etat_vu']);
            if ($hasNewSchema) {
                return;
            }
        }

        $this->dropForeignKeyIfExists('notification', 'FK_BF5476CA37BC4DC6');
        $this->dropForeignKeyIfExists('notification', 'FK_BF5476CA6B899279');
        $this->dropForeignKeyIfExists('notification', 'FK_BF5476CA62FF6CDF');
        $this->dropIndexIfExists('notification', 'IDX_BF5476CA37BC4DC6');
        $this->dropIndexIfExists('notification', 'IDX_BF5476CA6B899279');
        $this->dropIndexIfExists('notification', 'IDX_BF5476CA62FF6CDF');
        $this->disableForeignKeyChecks();
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD type VARCHAR(255) NOT NULL, ADD date_envoi DATETIME NOT NULL, ADD etat_vu VARCHAR(255) NOT NULL, DROP emitter_id, DROP patient_id, DROP consultation_id, DROP created_at, DROP priority, DROP status, CHANGE message message VARCHAR(255) NOT NULL, CHANGE link link VARCHAR(255) DEFAULT NULL
        SQL);
        $this->enableForeignKeyChecks();
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->disableForeignKeyChecks();
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD emitter_id INT DEFAULT NULL, ADD patient_id INT DEFAULT NULL, ADD consultation_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ADD priority VARCHAR(20) DEFAULT 'info' NOT NULL, ADD status VARCHAR(20) DEFAULT 'non_lu' NOT NULL, DROP type, DROP date_envoi, DROP etat_vu, CHANGE message message LONGTEXT NOT NULL, CHANGE link link VARCHAR(512) DEFAULT NULL
        SQL);
        $this->enableForeignKeyChecks();
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CA37BC4DC6 ON notification (emitter_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CA6B899279 ON notification (patient_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CA62FF6CDF ON notification (consultation_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA37BC4DC6 FOREIGN KEY (emitter_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA62FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)
        SQL);
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND CONSTRAINT_NAME = :constraint
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        SQL;

        $exists = (bool) $this->connection->fetchOne($sql, [
            'table' => $table,
            'constraint' => $constraint,
        ]);

        if ($exists) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraint));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND INDEX_NAME = :index
        SQL;

        $exists = (bool) $this->connection->fetchOne($sql, [
            'table' => $table,
            'index' => $index,
        ]);

        if ($exists) {
            $this->addSql(sprintf('DROP INDEX %s ON %s', $index, $table));
        }
    }

    private function disableForeignKeyChecks(): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
    }

    private function enableForeignKeyChecks(): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
