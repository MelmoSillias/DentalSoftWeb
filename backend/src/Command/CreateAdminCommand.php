<?php

// src/Command/CreateAdminCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = new User();
        $user->setUsername('Melmo');
        $user->setRoles(['ROLE_ADMIN']); // ✅ Correct
        $user->setPassword($this->passwordHasher->hashPassword($user, 'admin123')); // Hash sécurisé

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('✅ Admin créé avec succès !');
        return Command::SUCCESS;
    }
}
