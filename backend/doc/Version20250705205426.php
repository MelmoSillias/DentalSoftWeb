<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250705205426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['patient'])) {
            $columns = $sm->listTableColumns('patient');
            if (!isset($columns['referencement'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE patient ADD referencement VARCHAR(255) NOT NULL
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if ($sm->tablesExist(['patient'])) {
            $columns = $sm->listTableColumns('patient');
            if (isset($columns['referencement'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE patient DROP referencement
                SQL);
            }
        }
    }
}
