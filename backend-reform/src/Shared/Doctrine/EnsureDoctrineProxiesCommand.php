<?php

namespace App\Shared\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:doctrine:ensure-proxies',
    description: 'Écrit les proxies Doctrine manquants sans vider le cache applicatif.',
)]
class EnsureDoctrineProxiesCommand extends Command
{
    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $generated = 0;

        foreach ($this->managerRegistry->getManagers() as $name => $manager) {
            if (!$manager instanceof EntityManagerInterface) {
                continue;
            }

            $proxyDir = $manager->getConfiguration()->getProxyDir();
            if ($proxyDir === null || $proxyDir === '') {
                continue;
            }

            if (!is_dir($proxyDir) && !@mkdir($proxyDir, 0775, true) && !is_dir($proxyDir)) {
                $io->error(sprintf('Répertoire de proxies impossible à créer (%s) : %s', $name, $proxyDir));

                return Command::FAILURE;
            }

            if (!is_writable($proxyDir)) {
                $io->error(sprintf('Répertoire de proxies non inscriptible (%s) : %s', $name, $proxyDir));

                return Command::FAILURE;
            }

            $count = $manager->getProxyFactory()->generateProxyClasses(
                $manager->getMetadataFactory()->getAllMetadata(),
                $proxyDir,
            );
            $generated += $count;
            $io->writeln(sprintf('[ensure-proxies] %s : %d proxy(s) dans %s', $name, $count, $proxyDir));
        }

        if ($generated < 1) {
            $io->error('Aucun proxy Doctrine généré. Le conteneur ne doit pas servir le trafic dans cet état.');

            return Command::FAILURE;
        }

        $io->success(sprintf('%d proxy(s) Doctrine présent(s).', $generated));

        return Command::SUCCESS;
    }
}
