<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persistent idempotency receipts for authenticated API writes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_mutation_receipt (request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, fingerprint CHAR(64) NOT NULL, status_code SMALLINT DEFAULT NULL, response_body LONGTEXT DEFAULT NULL, response_headers JSON DEFAULT NULL, expires_at BIGINT NOT NULL, INDEX IDX_MUTATION_EXPIRY (expires_at), PRIMARY KEY(request_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_mutation_receipt');
    }
}
