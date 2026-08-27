<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add numero_passage to consultation and backfill per day by created_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD numero_passage INT DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $connection = $this->connection;
        $rows = $connection->fetchAllAssociative(
            'SELECT id, created_at FROM consultation WHERE created_at IS NOT NULL ORDER BY created_at ASC, id ASC'
        );

        $counters = [];
        foreach ($rows as $row) {
            $day = (new \DateTimeImmutable((string) $row['created_at']))->format('Y-m-d');
            $counters[$day] = ($counters[$day] ?? 0) + 1;
            $connection->executeStatement(
                'UPDATE consultation SET numero_passage = :numero WHERE id = :id',
                [
                    'numero' => $counters[$day],
                    'id' => $row['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP numero_passage');
    }
}
