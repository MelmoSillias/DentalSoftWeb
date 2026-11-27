<?php

namespace App\Communication\Infrastructure\Console;

use App\Communication\Application\Service\ShowcaseMailbox;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:vitrine:sync-mailbox', description: 'Importe les demandes de rendez-vous et notifie les utilisateurs.')]
final class SyncShowcaseMailboxCommand extends Command
{
    public function __construct(private ShowcaseMailbox $mailbox) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $imported = $this->mailbox->sync(background: true);
        if ($imported > 0) {
            $output->writeln(sprintf('%d nouveau(x) message(s).', $imported));
        }
        return Command::SUCCESS;
    }
}
