<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Separate cabinet services from consultation acts: drop acte attribution and add service_cabinet billing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE acte_medical DROP attribution');
        $this->addSql('CREATE TABLE service_cabinet (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, fiche_medicale_id INT DEFAULT NULL, designation VARCHAR(255) NOT NULL, quantite INT DEFAULT 1 NOT NULL, prix DOUBLE PRECISION NOT NULL, montant DOUBLE PRECISION NOT NULL, date_realisation DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, statut VARCHAR(20) DEFAULT \'effectue\' NOT NULL, INDEX IDX_SERVICE_CABINET_PATIENT (patient_id), INDEX IDX_SERVICE_CABINET_FICHE (fiche_medicale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture_cabinet (id INT AUTO_INCREMENT NOT NULL, service_id INT NOT NULL, date_facture DATETIME NOT NULL, montant DOUBLE PRECISION NOT NULL, is_reglee TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_FACTURE_CABINET_SERVICE (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE service_cabinet ADD CONSTRAINT FK_SERVICE_CABINET_PATIENT FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_cabinet ADD CONSTRAINT FK_SERVICE_CABINET_FICHE FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE facture_cabinet ADD CONSTRAINT FK_FACTURE_CABINET_SERVICE FOREIGN KEY (service_id) REFERENCES service_cabinet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD facture_cabinet_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_PAIEMENT_FACTURE_CABINET FOREIGN KEY (facture_cabinet_id) REFERENCES facture_cabinet (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PAIEMENT_FACTURE_CABINET ON paiement (facture_cabinet_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_PAIEMENT_FACTURE_CABINET');
        $this->addSql('DROP INDEX IDX_PAIEMENT_FACTURE_CABINET ON paiement');
        $this->addSql('ALTER TABLE paiement DROP facture_cabinet_id');
        $this->addSql('ALTER TABLE facture_cabinet DROP FOREIGN KEY FK_FACTURE_CABINET_SERVICE');
        $this->addSql('ALTER TABLE service_cabinet DROP FOREIGN KEY FK_SERVICE_CABINET_PATIENT');
        $this->addSql('ALTER TABLE service_cabinet DROP FOREIGN KEY FK_SERVICE_CABINET_FICHE');
        $this->addSql('DROP TABLE facture_cabinet');
        $this->addSql('DROP TABLE service_cabinet');
        $this->addSql("ALTER TABLE acte_medical ADD attribution VARCHAR(20) DEFAULT 'medecin' NOT NULL");
    }
}
