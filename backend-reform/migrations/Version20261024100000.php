<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Doctrine\GuardedSql;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration
{
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Store Web Push subscriptions for browser notifications';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('CREATE TABLE web_push_subscription (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, endpoint LONGTEXT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, p256dh VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, content_encoding VARCHAR(32) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_web_push_endpoint_hash (endpoint_hash), INDEX idx_web_push_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->guardedSql('ALTER TABLE web_push_subscription ADD CONSTRAINT FK_WEB_PUSH_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE web_push_subscription DROP FOREIGN KEY FK_WEB_PUSH_USER');
        $this->guardedSql('DROP TABLE web_push_subscription');
    }
}
