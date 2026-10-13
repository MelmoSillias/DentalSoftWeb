<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Doctrine\GuardedSql;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Store the user who created a patient';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE patient ADD created_by_id INT DEFAULT NULL');
        $this->guardedSql('ALTER TABLE patient ADD CONSTRAINT FK_PATIENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->guardedSql('CREATE INDEX IDX_PATIENT_CREATED_BY ON patient (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE patient DROP FOREIGN KEY FK_PATIENT_CREATED_BY');
        $this->guardedSql('DROP INDEX IDX_PATIENT_CREATED_BY ON patient');
        $this->guardedSql('ALTER TABLE patient DROP created_by_id');
    }
}
