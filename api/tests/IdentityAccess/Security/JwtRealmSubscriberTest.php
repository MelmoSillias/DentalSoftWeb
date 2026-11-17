<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Security;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Infrastructure\Security\JwtRealmSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class JwtRealmSubscriberTest extends TestCase
{
    public function testBothLegacyAndCanonicalPathsEnforceTheRealm(): void
    {
        foreach ([
            ['/api/patients','app',true], ['/api/patients','patient',false],
            ['/api/patient-portal/me/profile','patient',true], ['/api/patient-portal/me/profile','app',false],
            ['/api/portal-patient/me/profil','patient',true], ['/api/portal-patient/me/profil','app',false],
            ['/api/me','patient',true], ['/api/me','app',true], ['/api/me','platform',false], ['/api/me',null,false],
            ['/api/me/appointments/dates/2026-11-14','patient',false], ['/api/me/appointments/dates/2026-11-14','app',true],
            ['/api/me/notification-read-receipts','patient',true], ['/api/auth/session','patient',true],
        ] as [$path,$realm,$valid]) {
            $requests = new RequestStack(); $requests->push(Request::create($path));
            $event = new JWTDecodedEvent(['realm'=>$realm]);
            (new JwtRealmSubscriber($requests))->onDecoded($event);
            self::assertSame($valid,$event->isValid(),$path.' '.(string)$realm);
        }
    }

    public function testPasswordChangeInvalidatesAnAlreadyIssuedJwt(): void
    {
        $user = (new User())->setUsername('doctor')->setPassword('old-hash')->setRoles(['ROLE_MEDECIN']);
        $payload = ['realm'=>'app','credential_version'=>$user->getAuthenticationVersion()];
        $event = new JWTAuthenticatedEvent($payload,new UsernamePasswordToken($user,'api',$user->getRoles()));
        $subscriber = new JwtRealmSubscriber(new RequestStack());
        $subscriber->onAuthenticated($event);
        $user->setPassword('new-hash');
        $this->expectException(BadCredentialsException::class);
        $subscriber->onAuthenticated($event);
    }
}
