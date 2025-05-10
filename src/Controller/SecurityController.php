<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Security;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();  
        if ($user) {
            // Redirection dynamique selon le rôle de l'utilisateur
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->redirectToRoute('app_admin');
            } elseif (in_array('ROLE_MEDECIN', $user->getRoles())) {
                return $this->redirectToRoute('app_medecin');
            } elseif (in_array('ROLE_RECEPTIONNISTE', $user->getRoles())) {
                return $this->redirectToRoute('app_receptionniste');
            }

            // Si aucun rôle ne correspond, rediriger vers une page par défaut
            return $this->redirectToRoute('app_home');
        }

        // Gestion des erreurs de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
