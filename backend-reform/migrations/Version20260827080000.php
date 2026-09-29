<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Doctrine\GuardedSql;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827080000 extends AbstractMigration
{
    use GuardedSql;

    public function getDescription(): string
    {
        return 'Add numero_passage to consultation and backfill per day by created_at';
    }

    public function up(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE consultation ADD numero_passage INT DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        if (!$this->columnExistsNow('consultation', 'numero_passage')) {
            $this->write('  -> skip: backfill consultation.numero_passage');

            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, created_at, numero_passage FROM consultation WHERE created_at IS NOT NULL ORDER BY created_at ASC, id ASC'
        );

        $counters = [];
        foreach ($rows as $row) {
            $day = (new \DateTimeImmutable((string) $row['created_at']))->format('Y-m-d');
            if ($row['numero_passage'] !== null) {
                $counters[$day] = max($counters[$day] ?? 0, (int) $row['numero_passage']);
                continue;
            }

            $counters[$day] = ($counters[$day] ?? 0) + 1;
            $this->connection->executeStatement(
                'UPDATE consultation SET numero_passage = :numero WHERE id = :id AND numero_passage IS NULL',
                [
                    'numero' => $counters[$day],
                    'id' => $row['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->guardedSql('ALTER TABLE consultation DROP numero_passage');
    }
}
