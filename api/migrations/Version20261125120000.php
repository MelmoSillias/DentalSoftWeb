<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional diagnosis to each quote line.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('contenu_devis')->addColumn('diagnostic', 'string', ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('contenu_devis')->dropColumn('diagnostic');
    }
}
