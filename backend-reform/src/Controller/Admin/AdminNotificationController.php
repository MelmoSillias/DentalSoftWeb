<?php

namespace App\Controller\Admin;

use App\Enum\NotificationPriority;
use App\Form\AdminNotificationType;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminNotificationController extends AbstractController
{
    #[Route('/admin/notifications/envoyer', name: 'app_admin_notifications_send', methods: ['GET', 'POST'])]
    public function index(Request $request, NotificationService $notificationService): Response
    {
        $form = $this->createForm(AdminNotificationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $recipients = $data['recipients'] ?? [];
            $message = $data['message'] ?? '';
            $link = $data['link'] ?? null;
            $priority = $data['priority'] ?? NotificationPriority::INFO;
            $emitter = $this->getUser();

            $notificationService->notifyUsers($recipients, $message, $link, $priority, $emitter instanceof \App\IdentityAccess\Entity\User ? $emitter : null);

            $this->addFlash('success', 'Notifications envoyées avec succès.');

            return $this->redirectToRoute('app_admin_notifications_send');
        }

        return $this->render('admin/notifications/envoyer.html.twig', [
            'form' => $form->createView(),
            'active_page' => 'admin_notifications_send',
        ]);
    }
}
