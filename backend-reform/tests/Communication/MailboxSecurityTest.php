<?php

namespace App\Tests\Communication;

use App\Communication\Security\MailboxCredentialCipher;
use App\Communication\Security\MailboxHostPolicy;
use App\Communication\Security\VitrineVoter;
use App\Communication\Service\ShowcaseMailbox;
use App\IdentityAccess\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use App\Communication\Controller\Api\ShowcaseController;
use App\Communication\EventSubscriber\VitrinePrivacySubscriber;
use App\Patient\Controller\ApiPortalPatient\AppreciationController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Communication\Service\NotificationService;
use App\Settings\Entity\AppSetting;
use App\Settings\Service\InternetFeaturesGate;
use Doctrine\ORM\EntityRepository;

final class MailboxSecurityTest extends TestCase
{
    public function testOnlyActiveAdminAndReceptionCanAccessVitrine(): void
    {
        foreach (['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_MEDECIN', 'ROLE_PATIENT', 'ROLE_SECRETAIRE'] as $role) {
            $user = (new User())->setUsername('test')->setRoles([$role]);
            $token = new UsernamePasswordToken($user, 'api', [$role]);
            $expected = in_array($role, VitrineVoter::ROLES, true) ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED;
            self::assertSame($expected, (new VitrineVoter())->vote($token, null, [VitrineVoter::ACCESS]), $role);
            $user->setActive(false);
            self::assertSame(VoterInterface::ACCESS_DENIED, (new VitrineVoter())->vote($token, null, [VitrineVoter::ACCESS]));
        }
    }

    public function testCredentialsAreRandomizedAuthenticatedAndBoundToAccount(): void
    {
        $cipher = new MailboxCredentialCipher('unit-test-secret');
        $first = $cipher->encrypt('application-password', 'imap.example.org|contact@example.org');
        $second = $cipher->encrypt('application-password', 'imap.example.org|contact@example.org');
        self::assertNotSame($first, $second);
        self::assertSame('application-password', $cipher->decrypt($first, 'imap.example.org|contact@example.org'));
        foreach (['wrong-context', 'tampered', 'old-format'] as $case) {
            $value = $first;
            $context = 'imap.example.org|contact@example.org';
            if ($case === 'wrong-context') $context = 'attacker.example.org|contact@example.org';
            if ($case === 'tampered') {
                $bytes = base64_decode(substr($first, 5));
                $bytes[30] = chr(ord($bytes[30]) ^ 1);
                $value = 'gcm1:'.base64_encode($bytes);
            }
            if ($case === 'old-format') $value = base64_encode(random_bytes(48));
            try { $cipher->decrypt($value, $context); self::fail('Secret accepted: '.$case); }
            catch (\RuntimeException) { self::assertTrue(true); }
        }
    }

    public function testHostRequiresExactDeploymentAllowlist(): void
    {
        $policy = new MailboxHostPolicy(['imap.example.org']);
        $policy->assertAllowedName('imap.example.org');
        foreach (['localhost', '127.0.0.1', 'imap.example.org.attacker.org', 'imap.example.org/novalidate-cert', 'imap.example.org:143', '{imap.example.org}', 'attacker.org'] as $host) {
            try { $policy->assertAllowedName($host); self::fail('Host accepted: '.$host); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        $this->expectException(\InvalidArgumentException::class);
        (new MailboxHostPolicy([]))->assertAllowedName('imap.example.org');
    }

    public function testChangingServerCannotReuseStoredPasswordAndConfigNeverReturnsSecret(): void
    {
        $value = ['host' => 'imap.one.example', 'username' => 'contact', 'email' => 'contact@example.org', 'password' => 'gcm1:stored-secret'];
        $entry = (new AppSetting())->setKeyName('showcase_mailbox')->setValue($value);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($entry);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');
        $notifications = (new \ReflectionClass(NotificationService::class))->newInstanceWithoutConstructor();
        $mailbox = new ShowcaseMailbox($em, new MailboxCredentialCipher('secret'), $notifications, new InternetFeaturesGate(true), sys_get_temp_dir(), new MailboxHostPolicy(['imap.one.example', 'imap.two.example']));
        self::assertArrayNotHasKey('password', $mailbox->config());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ressaisissez');
        $mailbox->save(['host' => 'imap.two.example', 'username' => 'contact', 'email' => 'contact@example.org', 'password' => '']);
    }

    public function testPrivateReservedAndMixedDnsAnswersAreRejected(): void
    {
        MailboxHostPolicy::assertPublicAddresses(['8.8.8.8', '2001:4860:4860::8888']);
        foreach ([[], ['127.0.0.1'], ['10.1.2.3'], ['169.254.169.254'], ['192.168.1.1'], ['100.64.0.1'], ['::1'], ['fe80::1'], ['fc00::1'], ['::ffff:127.0.0.1'], ['8.8.8.8', '10.0.0.1']] as $addresses) {
            try { MailboxHostPolicy::assertPublicAddresses($addresses); self::fail('Non-public DNS answer accepted.'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testEmailAddressesCannotInjectHeadersOrMultipleRecipients(): void
    {
        self::assertTrue(ShowcaseMailbox::validEmail('patient+rdv@example.org'));
        foreach (["patient@example.org\r\nBcc: attacker@example.org", "patient@example.org\0", 'a@example.org,b@example.org', 'Patient <patient@example.org>', '-X/tmp/log', ''] as $address) {
            self::assertFalse(ShowcaseMailbox::validEmail($address));
        }
    }

    public function testApiGuardsUseVitrineVoterAndMailConfigurationAddsAdminGuard(): void
    {
        $controller = new \ReflectionClass(ShowcaseController::class);
        self::assertSame(VitrineVoter::ACCESS, $controller->getAttributes(IsGranted::class)[0]->newInstance()->attribute);
        self::assertSame('ROLE_ADMIN', $controller->getMethod('save')->getAttributes(IsGranted::class)[0]->newInstance()->attribute);
        foreach (['listAdminAppreciations', 'setAdminAppreciationPublished', 'deleteAdminAppreciation'] as $method) {
            $reflection = new \ReflectionMethod(AppreciationController::class, $method);
            self::assertSame(VitrineVoter::ACCESS, $reflection->getAttributes(IsGranted::class)[0]->newInstance()->attribute);
        }
    }

    public function testExhaustedConnectionAndSyncQuotaRejectBeforeAnyNetworkAccess(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $mailbox = (new \ReflectionClass(ShowcaseMailbox::class))->newInstanceWithoutConstructor();
        $controller = new ShowcaseController($em, $mailbox);
        $controller->setContainer(new Container());
        $limiter = new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $limiter->create('mailbox')->consume();
        self::assertSame(429, $controller->save(new Request(), $limiter)->getStatusCode());
        $response = $controller->sync($limiter);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->headers->get('Retry-After'));
    }

    public function testSensitiveResponsesAreNotCachedOrProfiledButPublicExportIsUnaffected(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects(self::exactly(2))->method('disable');
        $subscriber = new VitrinePrivacySubscriber($profiler);
        foreach (['/api/vitrine/mailbox', '/api/administration/appreciations', '/api/appreciations/public/export'] as $path) {
            $request = Request::create($path);
            $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
            $response = new Response();
            $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
            self::assertSame(!str_contains($path, '/public/'), $response->headers->hasCacheControlDirective('no-store'));
        }
    }
}
