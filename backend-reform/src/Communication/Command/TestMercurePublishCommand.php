<?php

namespace App\Communication\Command;

use App\Communication\Mercure\NotificationTopicGenerator;
use App\Communication\Service\MercureHealthService;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'app:mercure:test-publish',
    description: 'Teste la publication Mercure depuis l\'API (diagnostic + publish).',
)]
final class TestMercurePublishCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly MercureHealthService $mercureHealthService,
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly UserRepository $userRepository,
        private readonly string $mercureUrl,
        private readonly string $mercurePublicUrl,
        private readonly string $topicNamespace,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'Publier sur le topic de cet utilisateur')
            ->addOption('topic', 't', InputOption::VALUE_REQUIRED, 'Topic Mercure explicite (prioritaire sur --user-id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test publication Mercure');

        $io->section('Configuration');
        $io->listing([
            'MERCURE_URL = ' . $this->mercureUrl,
            'MERCURE_PUBLIC_URL = ' . $this->mercurePublicUrl,
            'MERCURE_TOPIC_NAMESPACE = ' . $this->topicNamespace,
        ]);

        $io->section('Connectivité hub');
        $health = $this->mercureHealthService->diagnose();
        $io->table(
            ['Check', 'Valeur'],
            [
                ['status', (string) ($health['status'] ?? 'unknown')],
                ['hub joignable', ($health['internalPublishReachable'] ?? false) ? 'oui' : 'non'],
                ['code HTTP (GET hub)', (string) ($health['internalPublishStatus'] ?? 'n/a')],
                ['erreur', (string) ($health['internalPublishError'] ?? '-')],
            ]
        );

        if (!($health['internalPublishReachable'] ?? false)) {
            $io->error('Le hub Mercure est injoignable depuis ce conteneur. Vérifiez MERCURE_URL (internal host Dokploy).');

            return Command::FAILURE;
        }

        $topic = $this->resolveTopic($input, $io);
        if ($topic === null) {
            return Command::FAILURE;
        }

        $payload = [
            'id' => 0,
            'title' => 'Test Mercure',
            'message' => 'Publication console ' . (new \DateTimeImmutable())->format(DATE_ATOM),
            'type' => 'info',
            'priority' => 'info',
            'status' => 'non_vu',
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'link' => null,
            'emitter' => 'app:mercure:test-publish',
        ];

        $io->section('Publication');
        $io->text('Topic : ' . $topic);

        try {
            $this->hub->publish(new Update(
                $topic,
                (string) json_encode($payload, JSON_THROW_ON_ERROR),
                false,
                'mercure-console-test-' . time(),
                'notification',
            ));

            $io->success('Publication Mercure réussie.');
            $io->note([
                'Si un admin est connecté sur ce topic, une notification test doit apparaître.',
                'Sinon, vérifiez au minimum que le hub ne renvoie pas d\'erreur 401/403 côté publish.',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error('Échec de publication Mercure : ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function resolveTopic(InputInterface $input, SymfonyStyle $io): ?string
    {
        $explicitTopic = $input->getOption('topic');
        if (is_string($explicitTopic) && trim($explicitTopic) !== '') {
            return trim($explicitTopic);
        }

        $userId = $input->getOption('user-id');
        if ($userId !== null) {
            $user = $this->userRepository->find((int) $userId);
            if (!$user instanceof User) {
                $io->error(sprintf('Utilisateur #%s introuvable.', $userId));

                return null;
            }

            return $this->topicGenerator->forUser($user);
        }

        $namespace = trim($this->topicNamespace, "/ \t\n\r\0\x0B") ?: 'default';

        return sprintf(
            '%s/instances/%s/test/console',
            rtrim($this->mercurePublicUrl, '/'),
            rawurlencode($namespace),
        );
    }
}
