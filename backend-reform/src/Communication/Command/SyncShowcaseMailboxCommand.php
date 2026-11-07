<?php

namespace App\Communication\Command;

use App\Communication\Service\ShowcaseMailbox;
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
        $output->writeln(sprintf('%d nouveau(x) message(s).', $this->mailbox->sync()));
        return Command::SUCCESS;
    }
}
