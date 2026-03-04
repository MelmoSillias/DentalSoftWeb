<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\ManualNotificationType;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class NotificationController extends AbstractController
{
    #[Route('/admin/notifications/envoi', name: 'app_admin_notifications_send')]
    public function send(Request $request, NotificationService $notificationService): Response
    {
        $form = $this->createForm(ManualNotificationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<User> $recipients */
            $recipients = $form->get('recipients')->getData();
            $priority = $form->get('priority')->getData();
            $message = trim((string) $form->get('message')->getData());
            $link = $form->get('link')->getData();

            $uniqueRecipients = [];
            foreach ($recipients as $recipient) {
                if (!$recipient instanceof User) {
                    continue;
                }

                $key = $recipient->getId() ?? spl_object_id($recipient);
                $uniqueRecipients[$key] = $recipient;
            }

            $emitter = $this->getUser() instanceof User ? $this->getUser() : null;
            $sent = 0;

            if ($message !== '') {
                foreach ($uniqueRecipients as $recipient) {
                    $notificationService->notify(
                        $recipient,
                        $message,
                        $priority,
                        $link ?: null,
                        null,
                        $emitter,
                    );

                    ++$sent;
                }
            }

            if ($sent > 0) {
                $this->addFlash('success', sprintf('%d notification(s) envoyée(s).', $sent));
            } else {
                $this->addFlash('warning', 'Aucun destinataire valide.');
            }

            return $this->redirectToRoute('app_admin_notifications_send');
        }

        return $this->render('admin/notifications/send.html.twig', [
            'form' => $form,
            'active_page' => 'admin_notifications',
        ]);
    }
}
