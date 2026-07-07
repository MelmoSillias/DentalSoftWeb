<?php

namespace App\Settings\Service;

use App\IdentityAccess\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseMaintenanceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, CP850');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        $fallback = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        return is_string($fallback) && $fallback !== '' ? $fallback : '[message non UTF-8]';
    }

    private function sanitizeMixed(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->toUtf8($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeMixed($item);
            }
        }

        return $value;
    }

    private function resolveExecutable(string $name): string
    {
        $finder = new ExecutableFinder();
        $path = $finder->find($name);

        if (!$path) {
            throw new \RuntimeException(sprintf('%s introuvable dans PATH. Installez MySQL client tools ou ajoutez le binaire au PATH.', $name));
        }

        return $path;
    }

    public function verifyAdminPassword(User $admin, string $password): bool
    {
        $value = trim($password);
        if ($value == '') {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($admin, $value);
    }

    /** @param string[] $formats */
    public function createBackup(array $formats = ['sql'], string $prefix = 'backup'): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static fn ($f) => strtolower((string) $f), $formats))));
        if ($normalized === []) {
            $normalized = ['sql'];
        }

        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');
        $backupDir = $this->ensureBackupDirectory();
        $baseName = sprintf('%s_%s', preg_replace('/[^a-z0-9_\-]/i', '_', $prefix) ?: 'backup', $timestamp);

        $sqlPath = null;
        if (in_array('sql', $normalized, true) || in_array('zip', $normalized, true)) {
            $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
            $this->dumpSqlToFile($sqlPath);
        }

        $jsonPath = null;
        if (in_array('json', $normalized, true)) {
            $jsonPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.json';
            $payload = $this->sanitizeMixed($this->buildApplicationJsonExport());
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            if (!is_string($encoded)) {
                throw new \RuntimeException('Encodage JSON export impossible: ' . json_last_error_msg());
            }

            if (file_put_contents($jsonPath, $encoded) === false) {
                throw new \RuntimeException('Écriture du fichier JSON export impossible.');
            }
        }

        $zipPath = null;
        if (in_array('zip', $normalized, true)) {
            $zipPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.zip';
            $this->createZipArchive($zipPath, $sqlPath, [
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'database' => $this->databaseParams()['dbname'],
                'prefix' => $prefix,
            ], $jsonPath);
        }

        return [
            'baseName' => $baseName,
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'sqlPath' => $sqlPath,
            'zipPath' => $zipPath,
            'jsonPath' => $jsonPath,
            'relativeSqlPath' => $sqlPath ? $this->toProjectRelativePath($sqlPath) : null,
            'relativeZipPath' => $zipPath ? $this->toProjectRelativePath($zipPath) : null,
            'relativeJsonPath' => $jsonPath ? $this->toProjectRelativePath($jsonPath) : null,
        ];
    }

    public function restoreSqlBackup(string $sqlPath): void
    {
        $absolute = $this->resolveAbsolutePath($sqlPath);
        if (!is_file($absolute)) {
            throw new \RuntimeException('Fichier de snapshot introuvable.');
        }

        $sql = file_get_contents($absolute);
        if ($sql === false) {
            throw new \RuntimeException('Lecture du fichier de snapshot impossible.');
        }

        $params = $this->databaseParams();
        $mysql = $this->resolveExecutable('mysql');
        $command = [
            $mysql,
            '--host=' . $params['host'],
            '--port=' . (string) $params['port'],
            '--user=' . $params['user'],
            '--password=' . $params['password'],
            '--default-character-set=utf8mb4',
            $params['dbname'],
        ];

        // Force foreign key / unique checks off for the whole import session. Some dumps
        // (created with --skip-comments / compact options, or by mariadb-dump) omit the
        // SET FOREIGN_KEY_CHECKS=0 directive, which makes the restore fail with foreign key
        // constraint errors when tables are dropped/created in a non-dependency order.
        $wrappedSql = "SET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n"
            . $sql
            . "\nSET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n";

        $process = new Process($command, $this->projectDir);
        $process->setTimeout(600);
        $process->setInput($wrappedSql);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($this->toUtf8($process->getErrorOutput() ?: $process->getOutput()));
            throw new \RuntimeException('Restauration SQL impossible: ' . $message);
        }

        $this->em->clear();
    }

    public function resetDatabaseDataPreservingSuperAdmin(): array
    {
        $connection = $this->em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        $superAdmin = $connection->fetchAssociative('SELECT * FROM `user` WHERE id = 1 LIMIT 1');
        if (!$superAdmin) {
            throw new \RuntimeException('Le super-admin id=1 est introuvable.');
        }

        $excluded = ['user', 'doctrine_migration_versions'];
        $tableNames = array_values(array_filter($tables, static fn (string $name) => !in_array($name, $excluded, true)));

        // NOTE: we intentionally use DELETE instead of TRUNCATE. In MySQL, TRUNCATE is a
        // DDL statement that triggers an implicit COMMIT, which ends the transaction opened
        // by beginTransaction() and later causes "There is no active transaction." on commit().
        $connection->beginTransaction();
        try {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($tableNames as $tableName) {
                $quoted = $connection->getDatabasePlatform()->quoteIdentifier($tableName);
                $connection->executeStatement('DELETE FROM ' . $quoted);
            }

            $connection->executeStatement('DELETE FROM `user` WHERE id <> 1');
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }

        // Best-effort reset of AUTO_INCREMENT counters (DDL, cannot run inside the transaction).
        foreach ($tableNames as $tableName) {
            try {
                $quoted = $connection->getDatabasePlatform()->quoteIdentifier($tableName);
                $connection->executeStatement('ALTER TABLE ' . $quoted . ' AUTO_INCREMENT = 1');
            } catch (\Throwable) {
                // Ignore tables without an AUTO_INCREMENT column.
            }
        }

        $this->em->clear();

        return [
            'superAdminUsername' => $superAdmin['username'] ?? null,
            'tablesTruncated' => count($tableNames),
        ];
    }

    private function dumpSqlToFile(string $targetSqlPath): void
    {
        $params = $this->databaseParams();
        $mysqldump = $this->resolveExecutable('mysqldump');

        $command = [
            $mysqldump,
            '--host=' . $params['host'],
            '--port=' . (string) $params['port'],
            '--user=' . $params['user'],
            '--password=' . $params['password'],
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--add-drop-table',
            '--skip-comments',
            '--result-file=' . $targetSqlPath,
            $params['dbname'],
        ];

        $process = new Process($command, $this->projectDir);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($this->toUtf8($process->getErrorOutput() ?: $process->getOutput()));
            throw new \RuntimeException('Sauvegarde SQL impossible: ' . $message);
        }
    }

    private function createZipArchive(string $zipPath, ?string $sqlPath, array $metadata, ?string $jsonPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive ZIP.');
        }

        if ($sqlPath && is_file($sqlPath)) {
            $zip->addFile($sqlPath, basename($sqlPath));
        }

        if ($jsonPath && is_file($jsonPath)) {
            $zip->addFile($jsonPath, basename($jsonPath));
        }

        $encodedMetadata = json_encode($this->sanitizeMixed($metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encodedMetadata)) {
            throw new \RuntimeException('Encodage metadata JSON impossible: ' . json_last_error_msg());
        }

        $zip->addFromString('metadata.json', $encodedMetadata);
        $zip->close();
    }

    private function buildApplicationJsonExport(): array
    {
        $connection = $this->em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        $data = [];
        foreach ($tables as $tableName) {
            $rows = $connection->fetchAllAssociative('SELECT * FROM ' . $connection->getDatabasePlatform()->quoteIdentifier($tableName));
            $data[$tableName] = $this->sanitizeMixed($rows);
        }

        return [
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'database' => $this->databaseParams()['dbname'],
            'tables' => $data,
        ];
    }

    private function ensureBackupDirectory(): string
    {
        $path = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Impossible de créer le dossier de sauvegarde.');
        }

        return $path;
    }

    private function resolveAbsolutePath(string $path): string
    {
        if (str_starts_with($path, $this->projectDir)) {
            return $path;
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function toProjectRelativePath(string $absolutePath): string
    {
        $normalizedProject = rtrim(str_replace('\\', '/', $this->projectDir), '/');
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedPath, $normalizedProject . '/')) {
            return substr($normalizedPath, strlen($normalizedProject) + 1);
        }

        return $normalizedPath;
    }

    /** @return array{host:string,port:int,user:string,password:string,dbname:string} */
    private function databaseParams(): array
    {
        $databaseUrl = (string) ($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '');
        if ($databaseUrl === '') {
            throw new \RuntimeException('DATABASE_URL introuvable.');
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            throw new \RuntimeException('DATABASE_URL invalide.');
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int) ($parts['port'] ?? 3306);
        $user = urldecode($parts['user'] ?? 'root');
        $password = urldecode($parts['pass'] ?? '');
        $path = $parts['path'] ?? '';
        $dbname = ltrim($path, '/');

        if ($dbname === '') {
            throw new \RuntimeException('Nom de base de données introuvable dans DATABASE_URL.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'dbname' => $dbname,
        ];
    }
}
