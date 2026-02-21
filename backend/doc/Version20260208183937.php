<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208183937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if (!$sm->tablesExist(['fiche_medicale'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_medicale (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, medecin_id INT DEFAULT NULL, created_at DATETIME NOT NULL, meta JSON NOT NULL, identification JSON NOT NULL, antecedents_allergies JSON NOT NULL, entretien JSON NOT NULL, examens JSON NOT NULL, bilans JSON NOT NULL, plan_traitement JSON NOT NULL, images_documents JSON NOT NULL, devis JSON NOT NULL, seance_en_cours JSON NOT NULL, INDEX IDX_20D23266B899279 (patient_id), INDEX IDX_20D23264F31A84 (medecin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }

        if ($sm->tablesExist(['fiche_medicale'])) {
            $fks = $sm->listTableForeignKeys('fiche_medicale');
            $hasPatientFk = false;
            $hasMedecinFk = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_20D23266B899279') $hasPatientFk = true;
                if ($fk->getName() === 'FK_20D23264F31A84') $hasMedecinFk = true;
            }
            if (!$hasPatientFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23266B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)
                SQL);
            }
            if (!$hasMedecinFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23264F31A84 FOREIGN KEY (medecin_id) REFERENCES employe (id)
                SQL);
            }
        }

        if ($sm->tablesExist(['contact_urgence'])) {
            $contactFks = $sm->listTableForeignKeys('contact_urgence');
            $contactFkName = null;
            foreach ($contactFks as $fk) {
                if (in_array('patient_id', $fk->getLocalColumns(), true)) {
                    $contactFkName = $fk->getName();
                    break;
                }
            }
            if ($contactFkName) {
                $this->addSql(sprintf('ALTER TABLE contact_urgence DROP FOREIGN KEY %s', $contactFkName));
            }

            $indexes = $sm->listTableIndexes('contact_urgence');
            if (isset($indexes['idx_e9524a4c6b899279']) && !isset($indexes['uniq_e9524a4c6b899279'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE contact_urgence DROP INDEX IDX_E9524A4C6B899279
                SQL);
            }
            if (!isset($indexes['uniq_e9524a4c6b899279'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE contact_urgence ADD UNIQUE INDEX UNIQ_E9524A4C6B899279 (patient_id)
                SQL);
            }

            if ($contactFkName) {
                $this->addSql(sprintf('ALTER TABLE contact_urgence ADD CONSTRAINT %s FOREIGN KEY (patient_id) REFERENCES patient (id)', $contactFkName));
            }
        }

        if ($sm->tablesExist(['employe'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE employe CHANGE coming_days_in_week coming_days_in_week JSON NOT NULL, CHANGE administrative_files administrative_files JSON NOT NULL
            SQL);
        }

        if ($sm->tablesExist(['fiche_observation'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE fiche_observation CHANGE tooths_check tooths_check JSON NOT NULL
            SQL);
        }

        if ($sm->tablesExist(['notification'])) {
            $columns = $sm->listTableColumns('notification');
            $fks = $sm->listTableForeignKeys('notification');
            $indexes = $sm->listTableIndexes('notification');

            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_NOTIFICATION_USER') {
                    $this->addSql(<<<'SQL'
                        ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_USER
                    SQL);
                    break;
                }
            }

            if (isset($columns['etat_vu'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification CHANGE etat_vu etat_vu VARCHAR(255) NOT NULL
                SQL);
            }
            if (isset($columns['priority'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification CHANGE priority priority VARCHAR(20) NOT NULL
                SQL);
            }

            $hasUserFk = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_BF5476CAA76ED395') {
                    $hasUserFk = true;
                    break;
                }
            }
            if (!$hasUserFk && isset($columns['user_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
                SQL);
            }

            if (isset($indexes['idx_notification_emitter']) && !isset($indexes['idx_bf5476ca37bc4dc6'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification RENAME INDEX idx_notification_emitter TO IDX_BF5476CA37BC4DC6
                SQL);
            }
        }

        if ($sm->tablesExist(['patient'])) {
            $columns = $sm->listTableColumns('patient');
            if (!isset($columns['email'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE patient ADD email VARCHAR(255) DEFAULT NULL
                SQL);
            }
            if (!isset($columns['profession'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE patient ADD profession VARCHAR(255) DEFAULT NULL
                SQL);
            }
            if (!isset($columns['lieu_naissance'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE patient ADD lieu_naissance VARCHAR(255) DEFAULT NULL
                SQL);
            }
        }

        if ($sm->tablesExist(['user'])) {
            $this->addSql(<<<'SQL'
                ALTER TABLE user CHANGE roles roles JSON NOT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23266B899279
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23264F31A84
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_medicale
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_urgence DROP INDEX UNIQ_E9524A4C6B899279, ADD INDEX IDX_E9524A4C6B899279 (patient_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE employe CHANGE coming_days_in_week coming_days_in_week JSON NOT NULL COLLATE `utf8mb4_bin`, CHANGE administrative_files administrative_files JSON NOT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_observation CHANGE tooths_check tooths_check JSON NOT NULL COLLATE `utf8mb4_bin`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification CHANGE etat_vu etat_vu VARCHAR(255) DEFAULT 'non_vu' NOT NULL, CHANGE priority priority VARCHAR(20) DEFAULT 'info' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification RENAME INDEX idx_bf5476ca37bc4dc6 TO IDX_NOTIFICATION_EMITTER
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE patient DROP email, DROP profession, DROP lieu_naissance
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COLLATE `utf8mb4_bin`
        SQL);
    }
}
