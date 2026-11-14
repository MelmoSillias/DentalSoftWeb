<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Security;

use App\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\IdentityAccess\Application\Port\RefreshAccessGuardInterface;
use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\DoctrineRefreshTokenRepository;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\UserRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class RefreshTokenIntegrationTest extends TestCase
{
    private EntityManager $em;
    private DoctrineRefreshTokenRepository $repository;
    private RefreshTokenService $service;
    private User $user;
    private AccessTokenIssuerInterface $issuer;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required.');
        $config = ORMSetup::createAttributeMetadataConfiguration(glob(dirname(__DIR__, 3).'/src/*/Domain/Entity'), true);
        $config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy(CASE_LOWER, true));
        $this->em = new EntityManager(DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]), $config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->user = (new User())->setUsername('doctor')->setPassword('hashed-password')->setRoles(['ROLE_MEDECIN']);
        $this->em->persist($this->user);
        $this->em->flush();
        $this->repository = new DoctrineRefreshTokenRepository($this->em);
        $this->issuer = $this->createMock(AccessTokenIssuerInterface::class);
        $this->issuer->method('issue')->willReturn('signed-access-token');
        $this->service = new RefreshTokenService($this->repository, $this->issuer, $this->createMock(RefreshAccessGuardInterface::class));
    }

    protected function tearDown(): void { if (isset($this->em)) $this->em->getConnection()->close(); }

    public function testRotationAndLogoutPreventReplayAndStoreOnlyHashes(): void
    {
        $first = $this->service->createTokenPair($this->user, AuthRealm::App, 'device');
        self::assertSame($first['token'], $first['access_token']);
        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM auth_refresh_token');
        self::assertSame(hash('sha256',$first['refresh_token']), $row['token_hash']);
        self::assertNotContains($first['refresh_token'], array_values($row));
        $second = $this->service->refresh($first['refresh_token'], AuthRealm::App, 'device');
        self::assertNotSame($first['refresh_token'], $second['refresh_token']);
        try { $this->service->refresh($first['refresh_token'], AuthRealm::App, 'device'); self::fail('Replay accepted'); }
        catch (\InvalidArgumentException) { self::assertTrue(true); }
        // wrapInTransaction closes an EM on a failed operation: use a fresh request's EM.
        $this->resetManager();
        $this->service->revoke($second['refresh_token'], AuthRealm::App, 'device');
        $this->expectException(\InvalidArgumentException::class);
        $this->service->refresh($second['refresh_token'], AuthRealm::App, 'device');
    }

    public function testStaleConcurrentReaderCannotConsumeTheSameToken(): void
    {
        $pair = $this->service->createTokenPair($this->user, AuthRealm::App, 'device');
        $otherEm = new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
        $other = new DoctrineRefreshTokenRepository($otherEm);
        $stale = $other->findByHash(hash('sha256',$pair['refresh_token']));
        $this->service->refresh($pair['refresh_token'], AuthRealm::App, 'device');
        self::assertFalse($other->consume($stale, new \DateTimeImmutable()));
    }

    public function testFailedIssuanceRollsBackTokenConsumption(): void
    {
        $pair = $this->service->createTokenPair($this->user, AuthRealm::App, 'device');
        $broken = $this->createMock(AccessTokenIssuerInterface::class);
        $broken->method('issue')->willThrowException(new \RuntimeException('signer unavailable'));
        $service = new RefreshTokenService($this->repository, $broken, $this->createMock(RefreshAccessGuardInterface::class));
        try { $service->refresh($pair['refresh_token'],AuthRealm::App,'device'); self::fail('Expected failure'); }
        catch (\RuntimeException) { self::assertTrue(true); }
        self::assertNull($this->em->getConnection()->fetchOne('SELECT revoked_at FROM auth_refresh_token WHERE token_hash = ?', [hash('sha256',$pair['refresh_token'])]));
    }

    public function testWrongRealmAndDeviceCannotRefresh(): void
    {
        $pair = $this->service->createTokenPair($this->user, AuthRealm::App, 'device');
        foreach ([[AuthRealm::Patient,'device'],[AuthRealm::App,'foreign-device']] as [$realm,$device]) {
            try { $this->service->refresh($pair['refresh_token'],$realm,$device); self::fail('Foreign session accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
            $this->resetManager();
        }
        self::assertArrayHasKey('access_token',$this->service->refresh($pair['refresh_token'],AuthRealm::App,'device'));
    }

    public function testEmailLookupRejectsAmbiguousAccounts(): void
    {
        $employee = (new \App\IdentityAccess\Domain\Entity\Employe())->setNom('Doctor')->setPrenom('One')->setFonction('Doctor')->setType('medecin')->setEmail('doctor@example.test')->setUser($this->user)
            ->setDateEmbauche(new \DateTime('2026-01-01'))->setTypeSalaire('fixe')->setTypeContrat('CDI');
        $this->em->persist($employee);
        $this->em->flush();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);
        $repository = new UserRepository($registry);
        self::assertSame($this->user,$repository->loadUserByIdentifier('DOCTOR@example.test'));
        self::assertSame($this->user,$repository->loadUserByIdentifier('doctor'));
        $other = (new User())->setUsername('doctor@example.test')->setPassword('hash')->setRoles(['ROLE_ADMIN']);
        $this->em->persist($other); $this->em->flush();
        $this->expectException(UserNotFoundException::class);
        $repository->loadUserByIdentifier('doctor@example.test');
    }

    private function resetManager(): void
    {
        $this->em = new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
        $this->repository = new DoctrineRefreshTokenRepository($this->em);
        $this->service = new RefreshTokenService($this->repository,$this->issuer,$this->createMock(RefreshAccessGuardInterface::class));
    }
}
