<?php

namespace App\Communication\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:webpush:generate-vapid', description: 'Génère une paire de clés VAPID à copier dans l\'environnement')]
final class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = VAPID::createVapidKeys();

        $io->warning('Conservez cette paire. La changer invalide tous les abonnements déjà enregistrés. Ne commitez pas la clé privée.');
        $io->writeln('VAPID_SUBJECT=mailto:vous@example.com');
        $io->writeln('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return Command::SUCCESS;
    }
}
