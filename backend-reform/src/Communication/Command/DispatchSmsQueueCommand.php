<?php

namespace App\Communication\Command;

use App\Communication\Message\ProcessSmsQueueMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:sms:dispatch-queue',
    description: 'Enfile un traitement asynchrone de la file SMS (consommé par le worker Messenger).',
)]
final class DispatchSmsQueueCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de SMS à traiter par exécution.', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));

        $this->messageBus->dispatch(new ProcessSmsQueueMessage($limit));

        $io->writeln(sprintf('ProcessSmsQueueMessage dispatché (limit=%d).', $limit));

        return Command::SUCCESS;
    }
}
