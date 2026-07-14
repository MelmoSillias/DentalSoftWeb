<?php

namespace App\Communication\Command;

use App\Communication\Service\SmsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sms:process-queue', description: 'Traite les SMS en attente dont la date d\'envoi est échue.')]
final class ProcessSmsQueueCommand extends Command
{
    public function __construct(private readonly SmsService $smsService)
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

        $result = $this->smsService->processQueue($limit);

        $io->success(sprintf(
            'File SMS traitée. Processés: %d, envoyés: %d, échoués: %d.',
            (int) ($result['processed'] ?? 0),
            (int) ($result['sent'] ?? 0),
            (int) ($result['failed'] ?? 0)
        ));

        $snapshot = $result['snapshot']['before'] ?? null;
        if (is_array($snapshot)) {
            // Libellés FR sans le mot "failed" : Dokploy colore sinon la ligne en error.
            $io->writeln(sprintf(
                '[info] État file avant traitement: en_attente_dus=%d, en_attente_programmes=%d, echecs_dus=%d, echecs_programmes=%d, echecs_epuises=%d, en_cours=%d, envoyes=%d, annules=%d.',
                (int) ($snapshot['pendingDue'] ?? 0),
                (int) ($snapshot['pendingScheduled'] ?? 0),
                (int) ($snapshot['failedDue'] ?? 0),
                (int) ($snapshot['failedScheduled'] ?? 0),
                (int) ($snapshot['failedExhausted'] ?? 0),
                (int) ($snapshot['sending'] ?? 0),
                (int) ($snapshot['sent'] ?? 0),
                (int) ($snapshot['cancelled'] ?? 0)
            ));

            if ((int) ($result['processed'] ?? 0) === 0 && !empty($snapshot['nextScheduledAt'])) {
                $io->writeln(sprintf(
                    '[info] Prochain SMS programmable/relançable prévu à partir de %s.',
                    (string) $snapshot['nextScheduledAt']
                ));
            }
        }

        return Command::SUCCESS;
    }
}