<?php

namespace App\IdentityAccess\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:devices:migrate-global',
    description: 'Migre les appareils par utilisateur vers une gestion globale unique par identifiant',
)]
class MigrateGlobalDevicesCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['user_device'])) {
            $io->success('Table user_device absente. Rien a migrer.');

            return Command::SUCCESS;
        }

        $columns = $schemaManager->listTableColumns('user_device');
        $hasUserId = array_key_exists('user_id', $columns);
        $hasRequestedBy = array_key_exists('requested_by_id', $columns);

        if (!$hasUserId && $hasRequestedBy) {
            $io->success('Migration deja effectuee.');

            return Command::SUCCESS;
        }

        if (!$hasUserId) {
            $io->warning('Colonne user_id absente sans requested_by_id. Executez doctrine:schema:update.');

            return Command::FAILURE;
        }

        $io->title('Migration des appareils vers gestion globale');

        try {
            if (!$hasRequestedBy) {
                $this->connection->executeStatement('ALTER TABLE user_device ADD requested_by_id INT DEFAULT NULL');
            }

            $this->connection->executeStatement(
                'UPDATE user_device SET requested_by_id = user_id WHERE requested_by_id IS NULL AND user_id IS NOT NULL'
            );

            $duplicates = $this->connection->fetchAllAssociative(
                'SELECT device_identifier, COUNT(*) AS total FROM user_device GROUP BY device_identifier HAVING COUNT(*) > 1'
            );

            foreach ($duplicates as $duplicate) {
                $identifier = (string) $duplicate['device_identifier'];
                $rows = $this->connection->fetchAllAssociative(
                    'SELECT id, status FROM user_device WHERE device_identifier = ? ORDER BY id ASC',
                    [$identifier]
                );

                $keepId = $this->pickKeeperId($rows);
                $removeIds = array_values(array_filter(
                    array_map(static fn (array $row): int => (int) $row['id'], $rows),
                    static fn (int $id): bool => $id !== $keepId
                ));

                if ($removeIds === []) {
                    continue;
                }

                $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
                $this->connection->executeStatement(
                    "UPDATE user_device_access_log SET device_id = ? WHERE device_id IN ($placeholders)",
                    [$keepId, ...$removeIds]
                );
                $this->connection->executeStatement(
                    "DELETE FROM user_device WHERE id IN ($placeholders)",
                    $removeIds
                );
            }

            $foreignKeys = $schemaManager->listTableForeignKeys('user_device');
            foreach ($foreignKeys as $foreignKey) {
                if (in_array('user_id', $foreignKey->getLocalColumns(), true)) {
                    $this->connection->executeStatement(
                        sprintf('ALTER TABLE user_device DROP FOREIGN KEY `%s`', $foreignKey->getName())
                    );
                }
            }

            $indexes = $schemaManager->listTableIndexes('user_device');
            foreach ($indexes as $index) {
                $name = $index->getName();
                if ($name === 'uniq_user_device_identifier' || $name === 'IDX_6C7DADB3A76ED395') {
                    $this->connection->executeStatement(sprintf('ALTER TABLE user_device DROP INDEX `%s`', $name));
                }
            }

            $this->connection->executeStatement('ALTER TABLE user_device DROP COLUMN user_id');

            if (!array_key_exists('uniq_device_identifier', $indexes)) {
                $this->connection->executeStatement(
                    'CREATE UNIQUE INDEX uniq_device_identifier ON user_device (device_identifier)'
                );
            }

            if (!$hasRequestedBy) {
                $existingFks = $this->connection->createSchemaManager()->listTableForeignKeys('user_device');
                $hasRequestedByFk = false;
                foreach ($existingFks as $foreignKey) {
                    if (in_array('requested_by_id', $foreignKey->getLocalColumns(), true)) {
                        $hasRequestedByFk = true;
                        break;
                    }
                }

                if (!$hasRequestedByFk) {
                    $this->connection->executeStatement(
                        'ALTER TABLE user_device ADD CONSTRAINT FK_user_device_requested_by FOREIGN KEY (requested_by_id) REFERENCES `user` (id) ON DELETE SET NULL'
                    );
                }
            }

            $io->success(sprintf('Migration terminee. %d identifiants dupliques consolides.', count($duplicates)));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    /** @param array<int, array{id:int|string, status:string}> $rows */
    private function pickKeeperId(array $rows): int
    {
        $priority = static fn (string $status): int => match ($status) {
            'approved' => 1,
            'pending' => 2,
            default => 3,
        };

        usort($rows, static function (array $left, array $right) use ($priority): int {
            $statusCompare = $priority((string) $left['status']) <=> $priority((string) $right['status']);
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            return (int) $right['id'] <=> (int) $left['id'];
        });

        return (int) $rows[0]['id'];
    }
}
