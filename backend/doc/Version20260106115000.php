<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes priority/emitter sur notification et création des index nécessaires';
    }

    public function up(Schema $schema): void
    {
        $this->dropForeignKeyIfExists('notification', 'FK_BF5476CAA76ED395');

        if (! $this->columnExists('notification', 'priority')) {
            $this->disableForeignKeyChecks();
            $this->addSql(<<<'SQL'
                ALTER TABLE notification
                ADD emitter_id INT DEFAULT NULL,
                ADD priority VARCHAR(20) DEFAULT 'info' NOT NULL,
                MODIFY message LONGTEXT NOT NULL,
                MODIFY link VARCHAR(255) DEFAULT NULL,
                MODIFY type VARCHAR(255) NOT NULL,
                MODIFY etat_vu VARCHAR(255) NOT NULL DEFAULT 'non_vu'
            SQL);
            $this->enableForeignKeyChecks();
        }

        $this->createIndexIfMissing('notification', 'IDX_NOTIFICATION_EMITTER', 'emitter_id');
        $this->createIndexIfMissing('notification', 'idx_notification_user', 'user_id');
        $this->createIndexIfMissing('notification', 'idx_notification_date', 'date_envoi');
        $this->createIndexIfMissing('notification', 'idx_notification_state', 'etat_vu');

        if (! $this->foreignKeyExists('notification', 'FK_NOTIFICATION_EMITTER')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_EMITTER FOREIGN KEY (emitter_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }

        if (! $this->foreignKeyExists('notification', 'FK_NOTIFICATION_USER')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }

        // Clean up potential legacy constraints that could be recreated by previous schema states
        $this->dropForeignKeyIfExists('notification', 'FK_BF5476CAA76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->dropForeignKeyIfExists('notification', 'FK_NOTIFICATION_EMITTER');
        $this->dropForeignKeyIfExists('notification', 'FK_NOTIFICATION_USER');

        $this->dropIndexIfExists('notification', 'IDX_NOTIFICATION_EMITTER');
        $this->dropIndexIfExists('notification', 'idx_notification_user');
        $this->dropIndexIfExists('notification', 'idx_notification_date');
        $this->dropIndexIfExists('notification', 'idx_notification_state');

        if ($this->columnExists('notification', 'priority') || $this->columnExists('notification', 'emitter_id')) {
            $this->disableForeignKeyChecks();
            $this->addSql(<<<'SQL'
                ALTER TABLE notification
                DROP emitter_id,
                DROP priority,
                MODIFY message VARCHAR(255) NOT NULL,
                MODIFY link VARCHAR(255) DEFAULT NULL,
                MODIFY type VARCHAR(255) NOT NULL,
                MODIFY etat_vu VARCHAR(255) NOT NULL
            SQL);
            $this->enableForeignKeyChecks();
        }

        if (! $this->foreignKeyExists('notification', 'FK_BF5476CAA76ED395')) {
            $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
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

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $constraint));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            $this->addSql(sprintf('DROP INDEX %s ON %s', $index, $table));
        }
    }

    private function createIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (! $this->indexExists($table, $index)) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $index, $table, $columns));
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        SQL;

        return (bool) $this->connection->fetchOne($sql, [
            'table' => $table,
            'column' => $column,
        ]);
    }

    private function indexExists(string $table, string $index): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND INDEX_NAME = :index
        SQL;

        return (bool) $this->connection->fetchOne($sql, [
            'table' => $table,
            'index' => $index,
        ]);
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND CONSTRAINT_NAME = :constraint
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        SQL;

        return (bool) $this->connection->fetchOne($sql, [
            'table' => $table,
            'constraint' => $constraint,
        ]);
    }
}
