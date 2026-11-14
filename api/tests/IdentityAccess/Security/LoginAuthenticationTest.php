<?php

namespace App\Tests\IdentityAccess\Security;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Repository\RefreshTokenRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class LoginAuthenticationTest extends KernelTestCase
{
    /**
     * @dataProvider credentials
     * @dataProvider canonicalCredentials
     */
    public function testLoginAudienceAndFailurePrivacy(string $path, ?array $roles, bool $active, string $password, int $status): void
    {
        $kernel = self::bootKernel();
        $user = (new User())->setUsername('login-test')->setRoles($roles ?? [])->setActive($active)
            ->setPassword(password_hash('correct-password', PASSWORD_BCRYPT));
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willReturnCallback(static function () use ($roles, $user) {
            if ($roles === null) throw new UserNotFoundException();
            return $user;
        });
        $canonical = str_contains($path, '/auth/login');
        self::getContainer()->set('security.user.provider.concrete.'.($canonical ? 'login_identifier_provider' : 'users_in_database'), $provider);
        $manager = $this->createMock(JWTTokenManagerInterface::class);
        $manager->expects($status === 200 ? self::once() : self::never())->method('create')->willReturn('signed-test-token');
        self::getContainer()->set('lexik_jwt_authentication.jwt_manager', $manager);
        $refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokens->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        self::getContainer()->set(RefreshTokenRepositoryInterface::class, $refreshTokens);

        $response = $kernel->handle(Request::create($path, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '192.0.2.'.random_int(1, 254),
        ], json_encode([$canonical ? 'email' : 'username' => 'login-test', 'password' => $password])));

        self::assertSame($status, $response->getStatusCode(), $response->getContent());
        self::assertFalse($response->headers->has('Set-Cookie'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $data = json_decode($response->getContent(), true);
        if ($status === 200) {
            self::assertSame('signed-test-token', $data['token']);
            self::assertSame($data['token'], $data['access_token']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $data['refresh_token']);
        } else {
            self::assertSame(['code' => 401, 'message' => 'Identifiants incorrects.'], $data);
            self::assertFalse($response->headers->has('Set-Cookie'));
        }
    }

    public function credentials(): iterable
    {
        $portal = '/api/login_patient_check';
        $staff = '/api/login_check';
        yield 'patient portal' => [$portal, ['ROLE_PATIENT'], true, 'correct-password', 200];
        foreach (['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'] as $role) {
            yield $role.' refused in portal' => [$portal, [$role], true, 'correct-password', 401];
            yield $role.' allowed in staff' => [$staff, [$role], true, 'correct-password', 200];
        }
        yield 'mixed account' => [$portal, ['ROLE_ADMIN', 'ROLE_PATIENT'], true, 'correct-password', 401];
        yield 'unknown account' => [$portal, null, true, 'correct-password', 401];
        yield 'wrong password' => [$portal, ['ROLE_PATIENT'], true, 'incorrect', 401];
        yield 'disabled patient' => [$portal, ['ROLE_PATIENT'], false, 'correct-password', 401];
        yield 'patient refused in staff' => [$staff, ['ROLE_PATIENT'], true, 'correct-password', 401];
        yield 'disabled admin' => [$staff, ['ROLE_ADMIN'], false, 'correct-password', 401];
    }

    public function canonicalCredentials(): iterable
    {
        foreach ($this->credentials() as $label => $case) {
            $case[0] = $case[0] === '/api/login_check' ? '/api/auth/login' : '/api/patient-portal/auth/login';
            yield 'canonical '.$label => $case;
        }
    }

    public function testTokenValidationRequiresAuthentication(): void
    {
        $response = self::bootKernel()->handle(Request::create('/api/token/validate'));
        self::assertSame(401, $response->getStatusCode());
    }

    public function testPatientCanValidateItsSessionButCannotReadStaffAppointments(): void
    {
        $kernel = self::bootKernel();
        $user = (new User())->setUsername('patient')->setPassword('hashed-password')->setRoles(['ROLE_PATIENT']);
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willReturn($user);
        self::getContainer()->set('security.user.provider.concrete.users_in_database', $provider);
        $manager = $this->createMock(JWTTokenManagerInterface::class);
        $manager->method('parse')->willReturn(['username'=>'patient','realm'=>'patient','credential_version'=>$user->getAuthenticationVersion()]);
        $manager->method('getUserIdClaim')->willReturn('username');
        self::getContainer()->set('lexik_jwt_authentication.jwt_manager', $manager);
        foreach (['/api/auth/session'=>200, '/api/me/appointments/dates/2026-11-14'=>403] as $path=>$status) {
            $response = $kernel->handle(Request::create($path,'GET',[],[],[],['HTTP_AUTHORIZATION'=>'Bearer signed-token']));
            self::assertSame($status,$response->getStatusCode(),$response->getContent());
        }
    }

    public function testDisabledAccountCannotUseAnExistingJwt(): void
    {
        $kernel = self::bootKernel();
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willReturn(
            (new User())->setUsername('disabled')->setRoles(['ROLE_PATIENT'])->setActive(false)
        );
        self::getContainer()->set('security.user.provider.concrete.users_in_database', $provider);
        $manager = $this->createMock(JWTTokenManagerInterface::class);
        $manager->method('parse')->willReturn(['username' => 'disabled']);
        $manager->method('getUserIdClaim')->willReturn('username');
        self::getContainer()->set('lexik_jwt_authentication.jwt_manager', $manager);
        $response = $kernel->handle(Request::create('/api/token/validate', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer previously-issued-jwt',
        ]));
        self::assertSame(401, $response->getStatusCode());
    }

    public function testRepeatedFailuresAreThrottledBeforeJwtCreation(): void
    {
        $kernel = self::bootKernel();
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());
        self::getContainer()->set('security.user.provider.concrete.users_in_database', $provider);
        $identifier = 'throttle-'.bin2hex(random_bytes(8));
        for ($i = 0; $i < 6; ++$i) {
            $response = $kernel->handle(Request::create('/api/login_patient_check', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '198.51.100.42',
            ], json_encode(['username' => $identifier, 'password' => 'incorrect'])));
            self::assertSame($i < 5 ? 401 : 429, $response->getStatusCode(), $response->getContent());
            self::assertArrayNotHasKey('token', json_decode($response->getContent(), true));
        }
    }
}
