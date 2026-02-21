<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250705112245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['consultation'])) {
            $columns = $sm->listTableColumns('consultation');
            $indexes = $sm->listTableIndexes('consultation');
            $fks = $sm->listTableForeignKeys('consultation');

            if (!isset($columns['facture_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation ADD facture_id INT DEFAULT NULL
                SQL);
            }

            $hasFk = false;
            foreach ($fks as $fk) {
                if (in_array('facture_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation ADD CONSTRAINT FK_964685A67F2DEE08 FOREIGN KEY (facture_id) REFERENCES devis (id)
                SQL);
            }

            if (!isset($indexes['uniq_964685a67f2dee08'])) {
                $this->addSql(<<<'SQL'
                    CREATE UNIQUE INDEX UNIQ_964685A67F2DEE08 ON consultation (facture_id)
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['consultation'])) {
            $columns = $sm->listTableColumns('consultation');
            $indexes = $sm->listTableIndexes('consultation');
            $fks = $sm->listTableForeignKeys('consultation');

            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_964685A67F2DEE08') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE consultation DROP FOREIGN KEY FK_964685A67F2DEE08
                    SQL);
                    break;
                }
            }

            if (isset($indexes['uniq_964685a67f2dee08'])) {
                $this->addSql(<<<'SQL'
                    DROP INDEX UNIQ_964685A67F2DEE08 ON consultation
                SQL);
            }

            if (isset($columns['facture_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation DROP facture_id
                SQL);
            }
        }
    }
}
