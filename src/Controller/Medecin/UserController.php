<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Employe;
use App\Repository\UserRepository;
use App\Repository\EmployeRepository;
use App\Form\UserType;

final class UserController extends AbstractController
{
#[Route('/admin/utilisateurs', name: 'app_admin_utilisateurs')]
public function UsersIndex(UserRepository $userRepository, EmployeRepository $employeRepository): Response
    {
        $users = $userRepository->findAll();
        $userEmployees = [];

        // Pour chaque utilisateur, recherche l'employé associé (s'il existe)
        foreach ($users as $user) {
            $employee = $employeRepository->findOneBy(['user' => $user]);
            if ($employee) {
                $userEmployees[$user->getId()] = $employee;
            }
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'userEmployees' => $userEmployees,
            // Vous pouvez également passer la liste complète des employés pour le formulaire d'ajout
            'employees' => $employeRepository->findAll(),
            'active_page' => 'utilisateurs'
        ]);
    }
}
