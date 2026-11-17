<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Allow insurance invoices for cabinet services with frozen billing lines'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture_assurance MODIFY consultation_id INT DEFAULT NULL, ADD service_cabinet_id INT DEFAULT NULL, ADD service_snapshot JSON DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FA_SERVICE_CABINET ON facture_assurance (service_cabinet_id)');
        $this->addSql('ALTER TABLE facture_assurance ADD CONSTRAINT FK_FA_SERVICE_CABINET FOREIGN KEY (service_cabinet_id) REFERENCES service_cabinet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE facture_assurance ADD CONSTRAINT CHK_FA_ORIGIN CHECK ((consultation_id IS NOT NULL AND service_cabinet_id IS NULL) OR (consultation_id IS NULL AND service_cabinet_id IS NOT NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM facture_assurance WHERE service_cabinet_id IS NOT NULL') > 0, 'Des factures assurance de services cabinet existent.');
        $this->addSql('ALTER TABLE facture_assurance DROP CONSTRAINT CHK_FA_ORIGIN');
        $this->addSql('ALTER TABLE facture_assurance DROP FOREIGN KEY FK_FA_SERVICE_CABINET');
        $this->addSql('DROP INDEX UNIQ_FA_SERVICE_CABINET ON facture_assurance');
        $this->addSql('ALTER TABLE facture_assurance DROP service_cabinet_id, DROP service_snapshot, MODIFY consultation_id INT NOT NULL');
    }
}
