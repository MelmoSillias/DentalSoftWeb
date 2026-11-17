<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Revocable, rotating refresh tokens for staff and patient sessions.'; }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('auth_refresh_token');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('token_hash', 'string', ['length' => 64]);
        $table->addColumn('realm', 'string', ['length' => 16]);
        $table->addColumn('device_hash', 'string', ['length' => 64]);
        $table->addColumn('credential_hash', 'string', ['length' => 64]);
        $table->addColumn('expires_at', 'datetime_immutable');
        $table->addColumn('revoked_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['token_hash'], 'UNIQ_AUTH_REFRESH_HASH');
        $table->addIndex(['user_id'], 'IDX_AUTH_REFRESH_USER');
        $table->addForeignKeyConstraint('user', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_AUTH_REFRESH_USER');
    }

    public function down(Schema $schema): void { $schema->dropTable('auth_refresh_token'); }
}
