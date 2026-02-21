<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223131015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if (!$sm->tablesExist(['ordonnance'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE ordonnance (id INT AUTO_INCREMENT NOT NULL, consultation_id INT NOT NULL, date DATETIME NOT NULL, medecin_nom VARCHAR(255) DEFAULT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_924B326C62FF6CDF (consultation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if (!$sm->tablesExist(['ordonnance_ligne'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE ordonnance_ligne (id INT AUTO_INCREMENT NOT NULL, ordonnance_id INT NOT NULL, designation VARCHAR(255) NOT NULL, posologie VARCHAR(255) DEFAULT NULL, frequence VARCHAR(255) DEFAULT NULL, duree VARCHAR(255) DEFAULT NULL, quantite INT DEFAULT NULL, instructions LONGTEXT DEFAULT NULL, INDEX IDX_CB9DA07A2BF23B8F (ordonnance_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if ($sm->tablesExist(['ordonnance'])) {
            $fks = $sm->listTableForeignKeys('ordonnance');
            $hasFk = false;
            foreach ($fks as $fk) {
                if (in_array('consultation_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE ordonnance ADD CONSTRAINT FK_924B326C62FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)
                SQL);
            }
        }

        if ($sm->tablesExist(['ordonnance_ligne'])) {
            $fks = $sm->listTableForeignKeys('ordonnance_ligne');
            $hasFk = false;
            foreach ($fks as $fk) {
                if (in_array('ordonnance_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE ordonnance_ligne ADD CONSTRAINT FK_CB9DA07A2BF23B8F FOREIGN KEY (ordonnance_id) REFERENCES ordonnance (id)
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['ordonnance'])) {
            $fks = $sm->listTableForeignKeys('ordonnance');
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_924B326C62FF6CDF') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE ordonnance DROP FOREIGN KEY FK_924B326C62FF6CDF
                    SQL);
                    break;
                }
            }
        }

        if ($sm->tablesExist(['ordonnance_ligne'])) {
            $fks = $sm->listTableForeignKeys('ordonnance_ligne');
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_CB9DA07A2BF23B8F') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE ordonnance_ligne DROP FOREIGN KEY FK_CB9DA07A2BF23B8F
                    SQL);
                    break;
                }
            }
        }

        if ($sm->tablesExist(['ordonnance'])) {
            $this->addSql(<<<'SQL'
                DROP TABLE ordonnance
            SQL);
        }

        if ($sm->tablesExist(['ordonnance_ligne'])) {
            $this->addSql(<<<'SQL'
                DROP TABLE ordonnance_ligne
            SQL);
        }
    }
}
