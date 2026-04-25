<?php

namespace App\IdentityAccess\Controller\Profile;

use App\Communication\Entity\Notification;
use App\Enum\NotificationStatus;
use App\Communication\Repository\NotificationRepository;
use App\IdentityAccess\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil/notifications', name: 'app_profile_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request, NotificationRepository $notificationRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $filter = $request->query->get('filter', 'all');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $status = match ($filter) {
            'lu' => NotificationStatus::LU,
            'non_lu' => NotificationStatus::NON_LU,
            default => null,
        };

        $notifications = $notificationRepository->findByUserWithStatus($user, $status, $perPage, $offset);
        $total = $notificationRepository->countByUserWithStatus($user, $status);
        $totalPages = (int) ceil($total / $perPage);

        if ($request->isMethod('POST')) {
            $ids = $request->request->all('ids');
            if (is_array($ids) && $ids !== []) {
                $repo = $em->getRepository(Notification::class);
                $list = $repo->findBy(['id' => $ids, 'recipient' => $user]);
                /** @var Notification $notif */
                foreach ($list as $notif) {
                    $notif->setEtatVu('vu');
                }
                $em->flush();
                $this->addFlash('success', 'Notifications marquées comme lues.');
            }
            return $this->redirectToRoute('app_profile_notifications', ['filter' => $filter, 'page' => $page]);
        }

        return $this->render('profile/notifications.html.twig', [
            'notifications' => $notifications,
            'filter' => $filter,
            'page' => $page,
            'total_pages' => max(1, $totalPages),
            'total' => $total,
            'active_page' => 'profile',
        ]);
    }

    #[Route('/notifications/latest', name: 'app_notifications_latest', methods: ['GET'])]
    public function latest(NotificationRepository $notificationRepository, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $list = $notificationRepository->findLatestForUser($user, 9);
        $data = array_map(function (Notification $n) use ($urlGenerator) {
            return [
                'id' => $n->getId(),
                'message' => $n->getMessage(),
                'status' => $n->getEtatVu(),
                'priority' => $n->getPriority()?->value,
                'createdAt' => $n->getDateEnvoi()?->format('Y-m-d H:i'),
                'link' => $n->getLink(),
                'goUrl' => $urlGenerator->generate('app_notifications_go', ['id' => $n->getId()]),
            ];
        }, $list);

        return new JsonResponse($data);
    }

    #[Route('/notifications/mark-read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $ids = $request->toArray()['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return new JsonResponse(['updated' => 0]);
        }

        $repo = $em->getRepository(Notification::class);
        $list = $repo->findBy(['id' => $ids, 'recipient' => $user]);
        foreach ($list as $notif) {
            $notif->setEtatVu('vu');
        }
        $em->flush();

        return new JsonResponse(['updated' => count($list)]);
    }

    #[Route('/notifications/go/{id}', name: 'app_notifications_go', methods: ['GET'])]
    public function go(Notification $notification, EntityManagerInterface $em): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $notification->getUser()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        if ($notification->getEtatVu() === 'non_lu') {
            $notification->setEtatVu('vu');
            $em->flush();
        }

        $target = $notification->getLink();
        if ($target) {
            return new RedirectResponse($target);
        }

        return $this->redirectToRoute('app_profile_notifications');
    }
}
