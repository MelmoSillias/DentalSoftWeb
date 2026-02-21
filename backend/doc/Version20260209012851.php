<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209012851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['fiche_bilan'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_bilan (id INT AUTO_INCREMENT NOT NULL, fiche_medicale_id INT NOT NULL, formule_dentaire JSON NOT NULL COMMENT '(DC2Type:json)', radiographie_extra_buccale_hypothese LONGTEXT DEFAULT NULL, radiographie_intra_buccale_hypothese LONGTEXT DEFAULT NULL, nfs_detaillee LONGTEXT DEFAULT NULL, tp_tca_inr LONGTEXT DEFAULT NULL, uree LONGTEXT DEFAULT NULL, creatininemie LONGTEXT DEFAULT NULL, glycemie LONGTEXT DEFAULT NULL, diagnostic_positif LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_8E5FA57B9A99F4BC (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_document'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_document (id INT AUTO_INCREMENT NOT NULL, fiche_medicale_id INT NOT NULL, type VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, INDEX IDX_BE77B0E79A99F4BC (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_entretien'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_entretien (id INT AUTO_INCREMENT NOT NULL, fiche_medicale_id INT NOT NULL, motif_consultation LONGTEXT DEFAULT NULL, anamnese LONGTEXT DEFAULT NULL, allaitement TINYINT(1) DEFAULT NULL, grossesse_en_cours TINYINT(1) DEFAULT NULL, menstrues TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_AC366B329A99F4BC (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_entretien_affection'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_entretien_affection (id INT AUTO_INCREMENT NOT NULL, entretien_id INT NOT NULL, nom VARCHAR(150) NOT NULL, est_presente TINYINT(1) DEFAULT NULL, details LONGTEXT DEFAULT NULL, INDEX IDX_269310C548DCEA2 (entretien_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_entretien_habitude'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_entretien_habitude (id INT AUTO_INCREMENT NOT NULL, entretien_id INT NOT NULL, type VARCHAR(50) NOT NULL, est_presente TINYINT(1) DEFAULT NULL, quantite LONGTEXT DEFAULT NULL, INDEX IDX_16366E7B548DCEA2 (entretien_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_entretien_medicament'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_entretien_medicament (id INT AUTO_INCREMENT NOT NULL, entretien_id INT NOT NULL, nom VARCHAR(100) NOT NULL, est_utilise TINYINT(1) DEFAULT NULL, details LONGTEXT DEFAULT NULL, INDEX IDX_1FC483548DCEA2 (entretien_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_entretien_question'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_entretien_question (id INT AUTO_INCREMENT NOT NULL, entretien_id INT NOT NULL, question VARCHAR(255) NOT NULL, reponse TINYINT(1) DEFAULT NULL, `precision` LONGTEXT DEFAULT NULL, INDEX IDX_B01C196A548DCEA2 (entretien_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_examen'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_examen (id INT AUTO_INCREMENT NOT NULL, fiche_medicale_id INT NOT NULL, occlusion VARCHAR(255) DEFAULT NULL, mediane VARCHAR(255) DEFAULT NULL, classes_angle VARCHAR(255) DEFAULT NULL, vestibules VARCHAR(255) DEFAULT NULL, hbd VARCHAR(255) DEFAULT NULL, brossage VARCHAR(255) DEFAULT NULL, soccu VARCHAR(255) DEFAULT NULL, cinematique_mandibulaire VARCHAR(255) DEFAULT NULL, ouverture_buccale VARCHAR(255) DEFAULT NULL, temperature_buccale VARCHAR(255) DEFAULT NULL, amplitude_ouverture VARCHAR(255) DEFAULT NULL, bruits_articulaires VARCHAR(255) DEFAULT NULL, tissus_mous_table JSON NOT NULL COMMENT '(DC2Type:json)', tissus_durs_table JSON NOT NULL COMMENT '(DC2Type:json)', examen_canaux_excreteurs LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_E750223B9A99F4BC (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_examen_item'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_examen_item (id INT AUTO_INCREMENT NOT NULL, examen_id INT NOT NULL, categorie VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, est_present TINYINT(1) DEFAULT NULL, details LONGTEXT DEFAULT NULL, INDEX IDX_E377F0BC5C8659A (examen_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_examen_labo'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_examen_labo (id INT AUTO_INCREMENT NOT NULL, examen_id INT NOT NULL, type VARCHAR(100) NOT NULL, observation LONGTEXT DEFAULT NULL, resultat LONGTEXT DEFAULT NULL, INDEX IDX_6F0B96FE5C8659A (examen_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_medicale'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_medicale (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, medecin_id INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_20D23266B899279 (patient_id), INDEX IDX_20D23264F31A84 (medecin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if (!$sm->tablesExist(['fiche_plan_traitement'])) {
            $this->addSql(<<<'SQL'
                CREATE TABLE fiche_plan_traitement (id INT AUTO_INCREMENT NOT NULL, fiche_medicale_id INT NOT NULL, plan_index INT DEFAULT NULL, type VARCHAR(50) NOT NULL, date_supposed DATE DEFAULT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_6A9B8F4E9A99F4BC (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
        if ($sm->tablesExist(['fiche_bilan'])) {
            $fks = $sm->listTableForeignKeys('fiche_bilan');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_8E5FA57B9A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_bilan ADD CONSTRAINT FK_8E5FA57B9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_document'])) {
            $fks = $sm->listTableForeignKeys('fiche_document');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_BE77B0E79A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_document ADD CONSTRAINT FK_BE77B0E79A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_entretien'])) {
            $fks = $sm->listTableForeignKeys('fiche_entretien');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_AC366B329A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_entretien ADD CONSTRAINT FK_AC366B329A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_entretien_affection'])) {
            $fks = $sm->listTableForeignKeys('fiche_entretien_affection');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_269310C548DCEA2') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_entretien_affection ADD CONSTRAINT FK_269310C548DCEA2 FOREIGN KEY (entretien_id) REFERENCES fiche_entretien (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_entretien_habitude'])) {
            $fks = $sm->listTableForeignKeys('fiche_entretien_habitude');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_16366E7B548DCEA2') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_entretien_habitude ADD CONSTRAINT FK_16366E7B548DCEA2 FOREIGN KEY (entretien_id) REFERENCES fiche_entretien (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_entretien_medicament'])) {
            $fks = $sm->listTableForeignKeys('fiche_entretien_medicament');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_1FC483548DCEA2') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_entretien_medicament ADD CONSTRAINT FK_1FC483548DCEA2 FOREIGN KEY (entretien_id) REFERENCES fiche_entretien (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_entretien_question'])) {
            $fks = $sm->listTableForeignKeys('fiche_entretien_question');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_B01C196A548DCEA2') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_entretien_question ADD CONSTRAINT FK_B01C196A548DCEA2 FOREIGN KEY (entretien_id) REFERENCES fiche_entretien (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_examen'])) {
            $fks = $sm->listTableForeignKeys('fiche_examen');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_E750223B9A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_examen ADD CONSTRAINT FK_E750223B9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_examen_item'])) {
            $fks = $sm->listTableForeignKeys('fiche_examen_item');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_E377F0BC5C8659A') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_examen_item ADD CONSTRAINT FK_E377F0BC5C8659A FOREIGN KEY (examen_id) REFERENCES fiche_examen (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_examen_labo'])) {
            $fks = $sm->listTableForeignKeys('fiche_examen_labo');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_6F0B96FE5C8659A') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_examen_labo ADD CONSTRAINT FK_6F0B96FE5C8659A FOREIGN KEY (examen_id) REFERENCES fiche_examen (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_medicale'])) {
            $fks = $sm->listTableForeignKeys('fiche_medicale');
            $hasPatient = false;
            $hasMedecin = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_20D23266B899279') $hasPatient = true;
                if ($fk->getName() === 'FK_20D23264F31A84') $hasMedecin = true;
            }
            if (!$hasPatient) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23266B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)
                SQL);
            }
            if (!$hasMedecin) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23264F31A84 FOREIGN KEY (medecin_id) REFERENCES employe (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['fiche_plan_traitement'])) {
            $fks = $sm->listTableForeignKeys('fiche_plan_traitement');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_6A9B8F4E9A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE fiche_plan_traitement ADD CONSTRAINT FK_6A9B8F4E9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }
        }
        if ($sm->tablesExist(['consultation'])) {
            $columns = $sm->listTableColumns('consultation');
            if (!isset($columns['fiche_medicale_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation ADD fiche_medicale_id INT DEFAULT NULL
                SQL);
            }
            if (!isset($columns['type'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation ADD type VARCHAR(50) DEFAULT NULL
                SQL);
            }

            $fks = $sm->listTableForeignKeys('consultation');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_964685A69A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE consultation ADD CONSTRAINT FK_964685A69A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }

            $indexes = $sm->listTableIndexes('consultation');
            if (!isset($indexes['idx_964685a69a99f4bc'])) {
                $this->addSql(<<<'SQL'
                    CREATE INDEX IDX_964685A69A99F4BC ON consultation (fiche_medicale_id)
                SQL);
            }
        }
        if ($sm->tablesExist(['contact_urgence'])) {
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
        }
        if ($sm->tablesExist(['devis'])) {
            $columns = $sm->listTableColumns('devis');
            if (!isset($columns['fiche_medicale_id'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE devis ADD fiche_medicale_id INT DEFAULT NULL
                SQL);
            }
            if (isset($columns['fiche_id']) && !$columns['fiche_id']->getNotnull()) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE devis CHANGE fiche_id fiche_id INT DEFAULT NULL
                SQL);
            }

            $fks = $sm->listTableForeignKeys('devis');
            $has = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_8B27C52B9A99F4BC') $has = true;
            }
            if (!$has) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)
                SQL);
            }

            $indexes = $sm->listTableIndexes('devis');
            if (!isset($indexes['idx_8b27c52b9a99f4bc'])) {
                $this->addSql(<<<'SQL'
                    CREATE INDEX IDX_8B27C52B9A99F4BC ON devis (fiche_medicale_id)
                SQL);
            }
        }
        if ($sm->tablesExist(['notification'])) {
            $fks = $sm->listTableForeignKeys('notification');
            foreach ($fks as $fk) {
                if (in_array($fk->getName(), ['FK_NOTIFICATION_USER', 'FK_NOTIFICATION_EMITTER'], true)) {
                    $this->addSql(sprintf('ALTER TABLE notification DROP FOREIGN KEY %s', $fk->getName()));
                }
            }
        }
        if ($sm->tablesExist(['notification'])) {
            $columns = $sm->listTableColumns('notification');
            if (isset($columns['etat_vu']) && isset($columns['priority'])) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification CHANGE etat_vu etat_vu VARCHAR(255) NOT NULL, CHANGE priority priority VARCHAR(20) NOT NULL
                SQL);
            }

            $fks = $sm->listTableForeignKeys('notification');
            $hasUserFk = false;
            $hasEmitterFk = false;
            foreach ($fks as $fk) {
                if ($fk->getName() === 'FK_BF5476CAA76ED395') $hasUserFk = true;
                if ($fk->getName() === 'FK_NOTIFICATION_EMITTER') $hasEmitterFk = true;
            }
            if (!$hasUserFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
                SQL);
            }

            $indexes = $sm->listTableIndexes('notification');
            if (isset($indexes['idx_notification_emitter'])) {
                $this->addSql(<<<'SQL'
                    DROP INDEX idx_notification_emitter ON notification
                SQL);
            }
            if (!isset($indexes['idx_bf5476ca37bc4dc6'])) {
                $this->addSql(<<<'SQL'
                    CREATE INDEX IDX_BF5476CA37BC4DC6 ON notification (emitter_id)
                SQL);
            }
            if (!$hasEmitterFk) {
                $this->addSql(<<<'SQL'
                    ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_EMITTER FOREIGN KEY (emitter_id) REFERENCES user (id) ON DELETE SET NULL
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation DROP FOREIGN KEY FK_964685A69A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE devis DROP FOREIGN KEY FK_8B27C52B9A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_bilan DROP FOREIGN KEY FK_8E5FA57B9A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_document DROP FOREIGN KEY FK_BE77B0E79A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_entretien DROP FOREIGN KEY FK_AC366B329A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_entretien_affection DROP FOREIGN KEY FK_269310C548DCEA2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_entretien_habitude DROP FOREIGN KEY FK_16366E7B548DCEA2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_entretien_medicament DROP FOREIGN KEY FK_1FC483548DCEA2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_entretien_question DROP FOREIGN KEY FK_B01C196A548DCEA2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_examen DROP FOREIGN KEY FK_E750223B9A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_examen_item DROP FOREIGN KEY FK_E377F0BC5C8659A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_examen_labo DROP FOREIGN KEY FK_6F0B96FE5C8659A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23266B899279
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23264F31A84
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_plan_traitement DROP FOREIGN KEY FK_6A9B8F4E9A99F4BC
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_bilan
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_entretien
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_entretien_affection
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_entretien_habitude
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_entretien_medicament
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_entretien_question
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_examen
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_examen_item
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_examen_labo
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_medicale
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fiche_plan_traitement
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_964685A69A99F4BC ON consultation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation DROP fiche_medicale_id, DROP type
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact_urgence DROP INDEX UNIQ_E9524A4C6B899279, ADD INDEX IDX_E9524A4C6B899279 (patient_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_8B27C52B9A99F4BC ON devis
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE devis DROP fiche_medicale_id, CHANGE fiche_id fiche_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA37BC4DC6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification CHANGE etat_vu etat_vu VARCHAR(255) DEFAULT 'non_vu' NOT NULL, CHANGE priority priority VARCHAR(20) DEFAULT 'info' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_bf5476ca37bc4dc6 ON notification
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_NOTIFICATION_EMITTER ON notification (emitter_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA37BC4DC6 FOREIGN KEY (emitter_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE patient DROP email, DROP profession, DROP lieu_naissance
        SQL);
    }
}
