<?php

namespace App\Controller\Profile;

use App\IdentityAccess\Entity\User;
use App\Communication\Repository\NotificationRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(
        Request $request,
        NotificationRepository $notificationRepository,
        EmployeRepository $employeRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $activityKey = $this->activityKey($user);
        $rawSessions = $session?->get($activityKey, []);
        $recentSessions = [];

        if (is_array($rawSessions)) {
            foreach ($rawSessions as $entry) {
                $recentSessions[] = [
                    'title' => $entry['title'] ?? 'Activité',
                    'subtitle' => $entry['subtitle'] ?? '',
                    'icon' => $entry['icon'] ?? 'fas fa-info-circle',
                    'badge' => $entry['badge'] ?? 'secondary',
                    'ip' => $entry['ip'] ?? null,
                    'userAgent' => $entry['userAgent'] ?? null,
                    'time' => isset($entry['timestamp']) ? $this->createDate($entry['timestamp']) : null,
                ];
            }
        }

        $filter = $request->query->getAlpha('notifications_filter') ?: 'all';
        if (!in_array($filter, ['all', 'read', 'unread'], true)) {
            $filter = 'all';
        }

        $notifications = $notificationRepository->findByFilter($user, $filter, 50);
        $unreadCount = $notificationRepository->countUnread($user);

        $employee = $employeRepository->findOneBy(['user' => $user]);

        return $this->render('profile/index.html.twig', [
            'recentSessions' => $recentSessions,
            'notifications' => $notifications,
            'notificationsFilter' => $filter,
            'notificationsUnreadCount' => $unreadCount,
            'employee' => $employee,
            'quickLinks' => $this->buildQuickLinks(),
            'active_page' => 'profile',
        ]);
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('change-password', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Votre session a expiré, veuillez réessayer.');
            return $this->redirectToRoute('app_profile');
        }

        $currentPassword = (string) $request->request->get('current_password');
        $newPassword = (string) $request->request->get('new_password');
        $confirmPassword = (string) $request->request->get('confirm_password');

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('danger', 'La confirmation du mot de passe ne correspond pas.');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('warning', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_profile');
        }

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

        return $this->redirectToRoute('app_profile');
    }

    private function buildQuickLinks(): array
    {
        $links = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $links[] = ['label' => 'Dashboard', 'route' => 'app_admin_dashboard', 'icon' => 'fas fa-tachometer-alt', 'variant' => 'primary'];
            $links[] = ['label' => 'Patients', 'route' => 'app_admin_patient', 'icon' => 'fas fa-user-plus', 'variant' => 'info'];
            $links[] = ['label' => 'Agenda', 'route' => 'app_admin_rendez_vous', 'icon' => 'fas fa-calendar', 'variant' => 'success'];
            $links[] = ['label' => 'Finances', 'route' => 'app_admin_finances', 'icon' => 'fas fa-coins', 'variant' => 'warning'];
        } elseif ($this->isGranted('ROLE_MEDECIN')) {
            $links[] = ['label' => 'Agenda', 'route' => 'app_medecin_agenda', 'icon' => 'fas fa-calendar', 'variant' => 'success'];
            $links[] = ['label' => 'Consultations', 'route' => 'app_medecin_consultations_pending', 'icon' => 'fas fa-stethoscope', 'variant' => 'primary'];
            $links[] = ['label' => 'Patients', 'route' => 'app_medecin_patient', 'icon' => 'fas fa-user-friends', 'variant' => 'info'];
        } elseif ($this->isGranted('ROLE_RECEPTION')) {
            $links[] = ['label' => 'Agenda', 'route' => 'app_reception_agenda', 'icon' => 'fas fa-calendar-day', 'variant' => 'success'];
            $links[] = ['label' => 'Patients', 'route' => 'app_reception_patient', 'icon' => 'fas fa-users', 'variant' => 'info'];
            $links[] = ['label' => 'Caisse', 'route' => 'app_reception_caisse', 'icon' => 'fas fa-cash-register', 'variant' => 'warning'];
        }

        return $links;
    }

    private function activityKey(User $user): string
    {
        return sprintf('user_activity_feed_%d', $user->getId());
    }

    private function createDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}

