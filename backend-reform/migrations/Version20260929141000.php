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
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Ensure AfrikSms webhook columns exist on sms_provider_config';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE sms_provider_config ADD webhook_base_url VARCHAR(255) DEFAULT NULL');
        $this->guardedSql('ALTER TABLE sms_provider_config ADD callback_notify_type SMALLINT DEFAULT 2 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE sms_provider_config DROP callback_notify_type');
        $this->guardedSql('ALTER TABLE sms_provider_config DROP webhook_base_url');
    }
}
