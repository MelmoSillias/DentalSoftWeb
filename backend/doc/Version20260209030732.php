<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209030732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale DROP identification, DROP antecedents_allergies, DROP entretien, DROP examens, DROP bilans, DROP plan_traitement, DROP images_documents, DROP devis, DROP seance_en_cours
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA37BC4DC6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA37BC4DC6 FOREIGN KEY (emitter_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE fiche_medicale ADD identification JSON NOT NULL COMMENT '(DC2Type:json)', ADD antecedents_allergies JSON NOT NULL COMMENT '(DC2Type:json)', ADD entretien JSON NOT NULL COMMENT '(DC2Type:json)', ADD examens JSON NOT NULL COMMENT '(DC2Type:json)', ADD bilans JSON NOT NULL COMMENT '(DC2Type:json)', ADD plan_traitement JSON NOT NULL COMMENT '(DC2Type:json)', ADD images_documents JSON NOT NULL COMMENT '(DC2Type:json)', ADD devis JSON NOT NULL COMMENT '(DC2Type:json)', ADD seance_en_cours JSON NOT NULL COMMENT '(DC2Type:json)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA37BC4DC6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA37BC4DC6 FOREIGN KEY (emitter_id) REFERENCES user (id)
        SQL);
    }
}
