<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notifications:cleanup', description: 'Supprime les notifications plus anciennes que 30 jours')]
final class NotificationsCleanupCommand extends Command
{
    public function __construct(private readonly NotificationService $notificationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-30 days');
        $removed = $this->notificationService->purgeOlderThan($threshold);

        $io->success(sprintf('%d notification(s) supprimée(s).', $removed));

        return Command::SUCCESS;
    }
}
