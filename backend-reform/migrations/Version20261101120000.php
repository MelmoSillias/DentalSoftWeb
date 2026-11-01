<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string { return 'INPS quarterly remittances and traceable payroll corrections'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE salary_payment ADD status VARCHAR(20) DEFAULT 'active' NOT NULL, ADD history JSON DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD request_id VARCHAR(64) DEFAULT NULL");
        $this->addSql("UPDATE salary_payment SET history = '[]'");
        $this->addSql('ALTER TABLE salary_payment MODIFY history JSON NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SALARY_REQUEST ON salary_payment (request_id)');
        $this->addSql('ALTER TABLE transaction ADD source VARCHAR(32) DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE transaction MODIFY montant NUMERIC(12,2) NOT NULL');
        $this->addSql("UPDATE transaction t INNER JOIN salary_payment p ON p.transaction_id = t.id SET t.source = 'payroll'");
        $this->addSql('CREATE TABLE inps_quarter (id INT NOT NULL, closed TINYINT(1) DEFAULT 0 NOT NULL, audit JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        for ($year = 2000; $year <= 2100; ++$year) {
            for ($quarter = 1; $quarter <= 4; ++$quarter) $this->addSql("INSERT INTO inps_quarter (id, closed, audit) VALUES (?, 0, '[]')", [$year * 10 + $quarter]);
        }
        $this->addSql('CREATE TABLE inps_remittance (id INT AUTO_INCREMENT NOT NULL, quarter_id INT NOT NULL, transaction_id INT NOT NULL, amount NUMERIC(12,2) NOT NULL, paid_at DATE NOT NULL, beneficiary VARCHAR(255) NOT NULL, reference VARCHAR(120) NOT NULL, note LONGTEXT DEFAULT NULL, request_id VARCHAR(64) NOT NULL, audit JSON NOT NULL, INDEX IDX_INPS_QUARTER (quarter_id), UNIQUE INDEX UNIQ_INPS_TRANSACTION (transaction_id), UNIQUE INDEX UNIQ_INPS_REQUEST (request_id), PRIMARY KEY(id), CONSTRAINT FK_INPS_QUARTER FOREIGN KEY (quarter_id) REFERENCES inps_quarter (id), CONSTRAINT FK_INPS_TRANSACTION FOREIGN KEY (transaction_id) REFERENCES transaction (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'Financial audit history must not be dropped automatically.');
    }
}
