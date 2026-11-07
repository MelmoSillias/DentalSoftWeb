<?php

namespace App\Tests\Communication;

use App\CareDelivery\Entity\Consultation;
use App\Communication\Entity\Notification;
use App\Communication\Mercure\NotificationTopicGenerator;
use App\Communication\Message\SendWebPushMessage;
use App\Communication\Repository\NotificationRepository;
use App\Communication\Repository\WebPushSubscriptionRepository;
use App\Communication\Service\NotificationRecipientResolver;
use App\Communication\Service\NotificationRealtimePublisher;
use App\Communication\Service\NotificationService;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\UserRepository;
use App\Patient\Entity\Patient;
use App\Patient\Repository\AppreciationRepository;
use App\Patient\Service\AppreciationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReviewNotificationTest extends TestCase
{
    /** @dataProvider reviewKinds */
    public function testNewReviewNotifiesOnlyReceptionAndAdminViaMercureAndPush(string $kind): void
    {
        $roles = ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_MEDECIN', 'ROLE_PATIENT', 'ROLE_RECEPTION', 'ROLE_ADMIN'];
        $users = [];
        foreach ($roles as $index => $role) {
            $user = (new User())->setUsername('user'.$index)->setRoles([$role]);
            (new \ReflectionProperty(User::class, 'id'))->setValue($user, $index + 1);
            if ($index === 5) $user->setActive(false);
            if ($index === 6) $user->setNotificationsEnabled(false);
            $users[] = $user;
        }
        $repository = $this->createMock(UserRepository::class);
        $repository->method('findByRoles')->willReturnCallback(fn($requested) => array_filter($users, fn($u) => array_intersect($requested, $u->getRoles()) !== []));
        $em = $this->createMock(EntityManagerInterface::class);
        $notifications = [];
        $em->method('persist')->willReturnCallback(function ($entity) use (&$notifications) {
            if ($entity instanceof Notification) {
                (new \ReflectionProperty(Notification::class, 'id'))->setValue($entity, count($notifications) + 1);
                $notifications[] = $entity;
            }
        });
        $messages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($message) use (&$messages) { $messages[] = $message; return new Envelope($message); });
        $subscriptions = $this->createMock(WebPushSubscriptionRepository::class);
        $subscriptions->method('countForUser')->willReturn(1);
        $service = new NotificationService($this->createMock(NotificationRepository::class), $em, new NotificationRecipientResolver($repository),
            new NotificationRealtimePublisher($bus, new NotificationTopicGenerator('https://mercure.example.org', 'test'), new NullLogger()), $subscriptions, $bus);
        $appreciations = $this->createMock(AppreciationRepository::class);
        $reviews = new AppreciationService($appreciations, $em, $service);
        $patient = (new Patient())->setNom('Secret')->setPrenom('Patient');
        $payload = ['rating' => 5, 'comment' => 'Confidential review content'];
        if ($kind === 'anonymous') $review = $reviews->createAnonymous($payload + ['isPublished' => true]);
        elseif ($kind === 'patient') $review = $reviews->createForPatient($patient, $payload);
        else $review = $reviews->createForConsultation($patient, (new Consultation())->setPatient($patient), $payload);
        if ($kind === 'anonymous') self::assertFalse($review->isPublished(), 'Public input must not bypass moderation.');
        self::assertSame([1, 2, 3], array_map(fn($n) => $n->getUser()->getId(), $notifications));
        foreach ($notifications as $notification) {
            self::assertSame('/vitrine/avis-patients', $notification->getLink());
            self::assertStringNotContainsString('Secret', $notification->getMessage());
            self::assertStringNotContainsString('Confidential', $notification->getMessage());
        }
        self::assertCount(3, array_filter($messages, fn($m) => $m instanceof Update));
        self::assertCount(3, array_filter($messages, fn($m) => $m instanceof SendWebPushMessage));
    }

    public function reviewKinds(): array { return [['anonymous'], ['patient'], ['consultation']]; }
}
