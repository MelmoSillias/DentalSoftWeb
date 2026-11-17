<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Messagerie Vitrine et historique des réponses'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE showcase_message (id INT AUTO_INCREMENT NOT NULL, external_id VARCHAR(64) NOT NULL, sender VARCHAR(255) NOT NULL, subject VARCHAR(500) NOT NULL, body LONGTEXT NOT NULL, received_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) NOT NULL, replies JSON NOT NULL, UNIQUE INDEX UNIQ_SHOWCASE_EXTERNAL (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE showcase_message'); }
}
