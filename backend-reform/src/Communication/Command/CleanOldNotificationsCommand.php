<?php

namespace App\Communication\Command;

use App\Communication\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notifications:cleanup:legacy', description: 'Supprime les notifications âgées de plus de 30 jours.')]
class CleanOldNotificationsCommand extends Command
{
    public function __construct(private NotificationRepository $notificationRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable('-30 days');
        $deleted = $this->notificationRepository->deleteOlderThan($threshold);

        $io->success(sprintf('%d notification(s) supprimée(s) avant le %s.', $deleted, $threshold->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
