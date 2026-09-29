<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rejoue l'ajout des colonnes webhook si Version20260721193000 est déjà
 * marquée exécutée sur une base dont le schéma ne les contient pas.
 */
final class Version20260929141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure AfrikSms webhook columns exist on sms_provider_config';
    }

    public function up(Schema $schema): void
    {
        $this->addColumnIfMissing('sms_provider_config', 'webhook_base_url', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('sms_provider_config', 'callback_notify_type', 'SMALLINT DEFAULT 2 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->dropColumnIfExists('sms_provider_config', 'callback_notify_type');
        $this->dropColumnIfExists('sms_provider_config', 'webhook_base_url');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition));
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }
}
