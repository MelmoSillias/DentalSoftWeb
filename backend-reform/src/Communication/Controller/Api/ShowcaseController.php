<?php

namespace App\Communication\Controller\Api;

use App\Communication\Entity\ShowcaseMessage;
use App\Communication\Service\ShowcaseMailbox;
use App\Communication\Service\SmsService;
use App\Communication\Security\VitrineVoter;
use App\Patient\Entity\Patient;
use App\IdentityAccess\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/vitrine', name: 'api_vitrine_')]
#[IsGranted(VitrineVoter::ACCESS)]
final class ShowcaseController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private ShowcaseMailbox $mailbox) {}

    #[Route('/mailbox', name: 'mailbox_get', methods: ['GET'])]
    public function config(): JsonResponse
    {
        $response = $this->json($this->mailbox->config());
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    #[Route('/mailbox', name: 'mailbox_save', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function save(Request $request, #[Autowire(service: 'limiter.vitrine_mail_connect')] RateLimiterFactory $limiter): JsonResponse
    {
        if (!$limiter->create('mailbox')->consume()->isAccepted()) return $this->json(['error' => 'Trop de tentatives de connexion. Réessayez dans une minute.'], 429, ['Retry-After' => '60']);
        try { $this->mailbox->save($request->toArray()); }
        catch (\InvalidArgumentException $e) { return $this->json(['error' => $e->getMessage()], 400); }
        catch (\RuntimeException $e) { return $this->json(['error' => $e->getMessage()], 503); }
        return $this->config();
    }

    #[Route('/messages', name: 'messages', methods: ['GET'])]
    public function messages(): JsonResponse
    {
        $messages = $this->em->getRepository(ShowcaseMessage::class)->findBy([], ['receivedAt' => 'DESC'], 500);
        return $this->json(['items' => array_map(fn(ShowcaseMessage $m) => $m->toArray(), $messages)]);
    }

    #[Route('/messages/sync', name: 'sync', methods: ['POST'])]
    public function sync(#[Autowire(service: 'limiter.vitrine_mail_sync')] RateLimiterFactory $limiter): JsonResponse
    {
        if (!$limiter->create('mailbox')->consume()->isAccepted()) return $this->json(['error' => 'Synchronisation trop fréquente. Réessayez dans une minute.'], 429, ['Retry-After' => '60']);
        try { return $this->json(['imported' => $this->mailbox->sync()]); }
        catch (\InvalidArgumentException $e) { return $this->json(['error' => $e->getMessage()], 400); }
        catch (\RuntimeException $e) { return $this->json(['error' => $e->getMessage()], 503); }
    }

    #[Route('/messages/{id}', name: 'message_status', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function status(int $id, Request $request): JsonResponse
    {
        $message = $this->em->find(ShowcaseMessage::class, $id);
        if (!$message) { return $this->json(['error' => 'Message introuvable.'], 404); }
        $status = $request->toArray()['status'] ?? '';
        if (!in_array($status, ['new', 'read', 'handled'], true)) { return $this->json(['error' => 'Statut invalide.'], 400); }
        $message->status = $status;
        $this->em->flush();
        return $this->json($message->toArray());
    }

    #[Route('/messages/{id}/reply', name: 'reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(int $id, Request $request, SmsService $sms,
        #[Autowire(service: 'limiter.vitrine_mail_reply')] RateLimiterFactory $limiter): JsonResponse
    {
        if (!$limiter->create('mailbox')->consume()->isAccepted()) return $this->json(['error' => 'Limite d’envoi atteinte. Réessayez dans une minute.'], 429, ['Retry-After' => '60']);
        $message = $this->em->find(ShowcaseMessage::class, $id);
        if (!$message) { return $this->json(['error' => 'Message introuvable.'], 404); }
        $data = $request->toArray();
        $body = trim((string) ($data['body'] ?? ''));
        $channel = $data['channel'] ?? '';
        if ($body === '' || mb_strlen($body) > 10000 || !in_array($channel, ['email', 'sms'], true)) {
            return $this->json(['error' => 'Choisissez un canal et saisissez une réponse (10 000 caractères maximum).'], 400);
        }
        $delivery = ['status' => 'accepted'];
        if ($channel === 'sms') {
            $phone = trim((string) ($data['phone'] ?? ''));
            if (!preg_match('/^\+?[0-9]{8,15}$/D', $phone) || mb_strlen($body) > 160) {
                return $this->json(['error' => 'Téléphone invalide ou SMS supérieur à 160 caractères.'], 400);
            }
            $result = $sms->queueManual($phone, $body, type: 'vitrine', source: 'vitrine', metadata: ['messageId' => $id]);
            if (!$result['success']) { return $this->json(['error' => $result['error']], 503); }
            $delivery = ['status' => 'queued', 'queueId' => $result['queueId']];
        } else {
            try { $this->mailbox->sendEmail($message, $body); }
            catch (\RuntimeException $e) { return $this->json(['error' => $e->getMessage()], 503); }
        }
        $message->replies[] = ['channel' => $channel, 'body' => $body, 'recipient' => $channel === 'sms' ? $phone : $message->sender,
            'date' => (new \DateTimeImmutable())->format(DATE_ATOM), 'delivery' => $delivery, 'userId' => $this->getUser()?->getId()];
        $message->status = 'handled';
        $this->em->flush();
        return $this->json($message->toArray());
    }

    #[Route('/portals', name: 'portals', methods: ['GET'])]
    public function portals(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Patient::class)->createQueryBuilder('p')->leftJoin('p.portalUser', 'u')->addSelect('u')
            ->where('p.deletedAt IS NULL')->orderBy('p.nom', 'ASC')->setMaxResults(100);
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        if ($search !== '') {
            $qb->andWhere('LOWER(p.nom) LIKE :q OR LOWER(p.prenom) LIKE :q OR LOWER(p.numCarnet) LIKE :q OR LOWER(u.username) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($search).'%');
        }
        if ($request->query->getString('scope', 'opened') === 'opened') { $qb->andWhere('u.id IS NOT NULL'); }
        return $this->json(['items' => array_map(fn(Patient $p) => ['id' => $p->getId(), 'name' => $p->getFullName(),
            'numCarnet' => $p->getNumCarnet(), 'username' => $p->getPortalUser()?->getUsername(),
            'active' => $p->getPortalUser()?->isActive(), 'hasPortal' => $p->getPortalUser() !== null], $qb->getQuery()->getResult())]);
    }

    #[Route('/portals/{id}/username', name: 'portal_username', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function username(int $id, Request $request): JsonResponse
    {
        $patient = $this->em->find(Patient::class, $id);
        $user = $patient?->getDeletedAt() === null ? $patient?->getPortalUser() : null;
        if (!$user) { return $this->json(['error' => 'Compte patient introuvable.'], 404); }
        $username = trim((string) ($request->toArray()['username'] ?? ''));
        if (!preg_match('/^[\pL\pN][\pL\pN._@+-]{2,179}$/uD', $username)) { return $this->json(['error' => 'Identifiant invalide (3 à 180 caractères).'], 400); }
        $existing = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing && $existing->getId() !== $user->getId()) { return $this->json(['error' => 'Identifiant déjà utilisé.'], 409); }
        $user->setUsername($username);
        try { $this->em->flush(); }
        catch (UniqueConstraintViolationException) { return $this->json(['error' => 'Identifiant déjà utilisé.'], 409); }
        return $this->json(['success' => true]);
    }

    #[Route('/portals/{id}/reset-password', name: 'portal_reset', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetPassword(int $id, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $patient = $this->em->find(Patient::class, $id);
        $user = $patient?->getDeletedAt() === null ? $patient?->getPortalUser() : null;
        if (!$user) { return $this->json(['error' => 'Compte patient introuvable.'], 404); }
        $password = bin2hex(random_bytes(8));
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->flush();
        $response = $this->json(['username' => $user->getUsername(), 'password' => $password]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
