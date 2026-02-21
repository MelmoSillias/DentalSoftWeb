<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250701232234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['paiement_devis'])) {
            $columns = $sm->listTableColumns('paiement_devis');
            $indexes = $sm->listTableIndexes('paiement_devis');
            $fks = $sm->listTableForeignKeys('paiement_devis');

            if (!isset($columns['consultation_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE paiement_devis ADD consultation_id INT DEFAULT NULL
                SQL);
            }

            $hasFk = false;
            foreach ($fks as $fk) {
                if (in_array('consultation_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE paiement_devis ADD CONSTRAINT FK_23BF203462FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)
                SQL);
            }

            if (!isset($indexes['uniq_23bf203462ff6cdf'])) {
                $this->addSql(<<<'SQL'
                    CREATE UNIQUE INDEX UNIQ_23BF203462FF6CDF ON paiement_devis (consultation_id)
                SQL);
            }
        }

        if ($sm->tablesExist(['transaction'])) {
            $columns = $sm->listTableColumns('transaction');
            $indexes = $sm->listTableIndexes('transaction');
            $fks = $sm->listTableForeignKeys('transaction');

            if (!isset($columns['paiement_devis_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE transaction ADD paiement_devis_id INT DEFAULT NULL
                SQL);
            }

            $hasFk = false;
            foreach ($fks as $fk) {
                if (in_array('paiement_devis_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE transaction ADD CONSTRAINT FK_723705D18B7902E0 FOREIGN KEY (paiement_devis_id) REFERENCES paiement_devis (id)
                SQL);
            }

            if (!isset($indexes['uniq_723705d18b7902e0'])) {
                $this->addSql(<<<'SQL'
                    CREATE UNIQUE INDEX UNIQ_723705D18B7902E0 ON transaction (paiement_devis_id)
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['paiement_devis'])) {
            $columns = $sm->listTableColumns('paiement_devis');
            $indexes = $sm->listTableIndexes('paiement_devis');
            $fks = $sm->listTableForeignKeys('paiement_devis');

            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_23BF203462FF6CDF') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE paiement_devis DROP FOREIGN KEY FK_23BF203462FF6CDF
                    SQL);
                    break;
                }
            }

            if (isset($indexes['uniq_23bf203462ff6cdf'])) {
                $this->addSql(<<<'SQL'
                    DROP INDEX UNIQ_23BF203462FF6CDF ON paiement_devis
                SQL);
            }

            if (isset($columns['consultation_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE paiement_devis DROP consultation_id
                SQL);
            }
        }

        if ($sm->tablesExist(['transaction'])) {
            $columns = $sm->listTableColumns('transaction');
            $indexes = $sm->listTableIndexes('transaction');
            $fks = $sm->listTableForeignKeys('transaction');

            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_723705D18B7902E0') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE transaction DROP FOREIGN KEY FK_723705D18B7902E0
                    SQL);
                    break;
                }
            }

            if (isset($indexes['uniq_723705d18b7902e0'])) {
                $this->addSql(<<<'SQL'
                    DROP INDEX UNIQ_723705D18B7902E0 ON transaction
                SQL);
            }

            if (isset($columns['paiement_devis_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE transaction DROP paiement_devis_id
                SQL);
            }
        }
    }
}
