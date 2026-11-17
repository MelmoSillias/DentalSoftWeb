<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:auth:purge-refresh-tokens', description: 'Remove expired refresh tokens; active sessions are preserved.')]
final class PurgeRefreshTokensCommand extends Command
{
    public function __construct(private Connection $connection) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->connection->executeStatement('DELETE FROM auth_refresh_token WHERE expires_at <= ?', [new \DateTimeImmutable()], [Types::DATETIME_IMMUTABLE]);
        (new SymfonyStyle($input,$output))->success(sprintf('%d expired refresh tokens removed.',$count));
        return Command::SUCCESS;
    }
}
