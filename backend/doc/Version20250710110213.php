<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250710110213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['rdv'])) {
            $columns = $sm->listTableColumns('rdv');
            if (!isset($columns['duration'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE rdv ADD duration INT NOT NULL
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['rdv'])) {
            $columns = $sm->listTableColumns('rdv');
            if (isset($columns['duration'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE rdv DROP duration
                SQL);
            }
        }
    }
}
