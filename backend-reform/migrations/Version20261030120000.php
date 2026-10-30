<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Doctrine\GuardedSql;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030120000 extends AbstractMigration
{
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Add employee address and title plus payroll allowance and INPS fields';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE employe ADD adresse VARCHAR(255) DEFAULT NULL, ADD titre VARCHAR(255) DEFAULT NULL');
        $this->guardedSql("ALTER TABLE salary_payment ADD indemnite_speciale NUMERIC(12, 2) DEFAULT 0 NOT NULL, ADD inps NUMERIC(12, 2) DEFAULT 0 NOT NULL, ADD prime_base_amount NUMERIC(12, 2) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE salary_payment DROP indemnite_speciale, DROP inps, DROP prime_base_amount');
        $this->guardedSql('ALTER TABLE employe DROP adresse, DROP titre');
    }
}
