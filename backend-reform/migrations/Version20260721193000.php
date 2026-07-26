<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AfrikSms webhook fields to sms_provider_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sms_provider_config ADD webhook_base_url VARCHAR(255) DEFAULT NULL, ADD callback_notify_type SMALLINT DEFAULT 2 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sms_provider_config DROP webhook_base_url, DROP callback_notify_type');
    }
}
