<?php

namespace App\ClinicalRecord\Command;

use App\ClinicalRecord\Service\FicheMedicaleService;
use App\Patient\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:patients:ensure-fiches',
    description: 'Crée une fiche médicale pour chaque patient actif qui n\'en a pas',
)]
class EnsurePatientFichesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private FicheMedicaleService $ficheMedicaleService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le nombre de patients sans fiche sans créer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Assurance des fiches médicales patients');

        $patientIds = $this->em->createQueryBuilder()
            ->select('p.id')
            ->from(Patient::class, 'p')
            ->leftJoin('p.fichesMedicales', 'f')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('f.id IS NULL')
            ->getQuery()
            ->getSingleColumnResult();

        $count = count($patientIds);
        if ($count === 0) {
            $io->success('Tous les patients actifs ont déjà au moins une fiche médicale.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('%d patient(s) sans fiche médicale.', $count));

        if ($dryRun) {
            $io->note('Mode dry-run : aucune fiche créée.');

            return Command::SUCCESS;
        }

        $created = 0;
        foreach ($patientIds as $patientId) {
            $patient = $this->em->find(Patient::class, (int) $patientId);
            if (!$patient instanceof Patient || $patient->isDeleted()) {
                continue;
            }

            $this->ficheMedicaleService->createForPatient($patient);
            ++$created;

            if ($created % 50 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $io->success(sprintf('%d fiche(s) médicale(s) créée(s).', $created));

        return Command::SUCCESS;
    }
}
