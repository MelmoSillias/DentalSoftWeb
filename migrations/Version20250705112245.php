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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation ADD facture_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation ADD CONSTRAINT FK_964685A67F2DEE08 FOREIGN KEY (facture_id) REFERENCES devis (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_964685A67F2DEE08 ON consultation (facture_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation DROP FOREIGN KEY FK_964685A67F2DEE08
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_964685A67F2DEE08 ON consultation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE consultation DROP facture_id
        SQL);
    }
}
