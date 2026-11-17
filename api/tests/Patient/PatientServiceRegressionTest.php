<?php

namespace App\Tests\Patient;

use App\Billing\Infrastructure\Persistence\Doctrine\FactureRepository;
use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\ConsultationRepository;
use App\IdentityAccess\Domain\Entity\Employe;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\EmployeRepository;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\UserRepository;
use App\Patient\Application\Handler\PatientCollectionHandler;
use App\Patient\Application\Handler\PatientOverviewHandler;
use App\Patient\Application\Mapper\PatientSummaryMapper;
use App\Patient\Application\Mapper\PortalAccountMapper;
use App\Patient\Application\Policy\PatientReadScopePolicy;
use App\Patient\Application\ReadModel\PatientSummaryProvider;
use App\Patient\Application\Service\PatientService;
use App\Patient\Application\UseCase\ManagePatientPortalAccounts;
use App\Patient\Domain\Entity\Patient;
use App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository;
use App\Settings\Application\Service\GlobalSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PatientServiceRegressionTest extends TestCase
{
    private array $dependencies;
    private ArrayAdapter $cache;
    private PatientService $service;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->dependencies = [];
        $arguments = [];
        $collaborators = [
            PatientSummaryMapper::class, PatientSummaryProvider::class, PortalAccountMapper::class,
            PatientCollectionHandler::class, PatientOverviewHandler::class,
            ManagePatientPortalAccounts::class,
        ];
        foreach ((new \ReflectionMethod(PatientService::class, '__construct'))->getParameters() as $parameter) {
            $class = $parameter->getType()->getName();
            if (in_array($class, $collaborators, true)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            // Unrelated final legacy collaborators are never invoked by these facade paths.
            $dependency = $parameter->getName() === 'cache' ? $this->cache
                : ($reflection->isFinal() ? $reflection->newInstanceWithoutConstructor() : $this->createMock($class));
            $this->dependencies[$class] = $dependency;
            $arguments[$parameter->getName()] = $dependency;
        }
        $this->dependencies[UserRepository::class] = $this->createMock(UserRepository::class);
        $this->dependencies[UserPasswordHasherInterface::class] = $this->createMock(UserPasswordHasherInterface::class);
        $summaryMapper = new PatientSummaryMapper();
        $summaryProvider = new PatientSummaryProvider(
            $this->dependencies[ConsultationRepository::class],
            $this->dependencies[FactureRepository::class],
            $summaryMapper,
        );
        $accountMapper = new PortalAccountMapper();
        $scopePolicy = new PatientReadScopePolicy(
            $this->dependencies[EmployeRepository::class],
            $this->dependencies[GlobalSettingsService::class],
        );
        // Inject real, normally constructed application collaborators into the facade.
        $this->service = new PatientService(
            ...$arguments,
            summaryMapper: $summaryMapper,
            summaryProvider: $summaryProvider,
            portalAccountMapper: $accountMapper,
            collectionHandler: new PatientCollectionHandler(
                $this->dependencies[PatientRepository::class], $summaryProvider, $scopePolicy, $this->cache,
            ),
            overviewHandler: new PatientOverviewHandler(
                $this->dependencies[PatientRepository::class], $this->dependencies[ConsultationRepository::class],
                $this->dependencies[EntityManagerInterface::class], $scopePolicy,
            ),
            portalAccounts: new ManagePatientPortalAccounts(
                $this->dependencies[PatientRepository::class], $this->dependencies[UserRepository::class],
                $this->dependencies[EntityManagerInterface::class], $this->dependencies[UserPasswordHasherInterface::class],
                $accountMapper, $this->cache,
            ),
        );
    }

    private function patient(int $id = 7): Patient
    {
        $patient = (new Patient())->setNom('Doe')->setPrenom('Jane')
            ->setDateInscription(new \DateTime('2026-01-02 10:30'));
        (new \ReflectionProperty(Patient::class, 'id'))->setValue($patient, $id);

        return $patient;
    }

    public function testPaginatedCollectionPreservesNormalizationResponseAndCacheKey(): void
    {
        $patient = $this->patient();
        $this->dependencies[PatientRepository::class]->expects(self::once())->method('paginatePatients')
            ->with(1, 100, 'Jane', 'nom', 'desc')->willReturn(['items' => [$patient], 'total' => 1]);
        $this->dependencies[FactureRepository::class]->expects(self::once())->method('sumUnpaidResteByPatientIds')
            ->with([7])->willReturn([7 => 125]);
        $this->dependencies[ConsultationRepository::class]->method('findLatestClosedByPatient')->willReturn(null);

        $result = $this->service->listPatientsPaginated(-1, 500, 'Jane', 'nom', 'DESC');

        self::assertSame(1, $result['page']);
        self::assertSame(100, $result['limit']);
        self::assertSame('desc', $result['sortOrder']);
        self::assertSame('nom', $result['sortField']);
        self::assertSame(1, $result['total']);
        self::assertSame(125, $result['items'][0]['impayees']);
        self::assertTrue($this->cache->hasItem('patients.collection.global.1.100.' . sha1('Jane') . '.nom.desc'));
        self::assertSame($result, $this->service->listPatientsPaginated(0, 100, 'Jane', 'nom', 'desc'));
    }

    public static function unpaidBalanceCases(): iterable
    {
        yield 'present' => [[7 => 230], 230];
        yield 'missing' => [[], 0];
    }

    /** @dataProvider unpaidBalanceCases */
    public function testPublicUnpaidBalanceOperationUsesProvider(array $balances, int $expected): void
    {
        $this->dependencies[FactureRepository::class]->expects(self::once())->method('sumUnpaidResteByPatientIds')
            ->with([7])->willReturn($balances);
        $this->dependencies[ConsultationRepository::class]->expects(self::never())->method('findLatestClosedByPatient');

        self::assertSame($expected, $this->service->getPatientImpayees(7));
    }

    public function testDetailsReuseProviderSelectionAndPureUtilityMappingsWithoutLoadingBalances(): void
    {
        $closed = (new Consultation())->setStatut(1)->setNoteSeance('Controle')
            ->setCreatedAt(new \DateTimeImmutable('2026-03-04 11:12'));
        $patient = $this->patient()->setDerniereConsultation($closed)
            ->setPhoto('/uploads/patients/7/photo.jpg')->setSmsReceipt(true);
        $this->dependencies[PatientRepository::class]->method('find')->with(7)->willReturn($patient);
        $this->dependencies[ConsultationRepository::class]->expects(self::once())->method('findLatestClosedByPatient')
            ->with($patient)->willReturn(null);
        $this->dependencies[FactureRepository::class]->expects(self::never())->method('sumUnpaidResteByPatientIds');

        $result = $this->service->getPatientDetailsData(7);

        self::assertSame([
            'date' => '2026-03-04 11:12', 'motif' => 'Controle', 'medecin' => '',
        ], $result['derniereConsultation']);
        self::assertSame('/uploads/patients/7/photo.jpg', $result['photo']);
        self::assertTrue($result['smsPreferences']['receipt']);
        self::assertNull($result['insuranceProfile']);
    }

    public function testGlobalUnpaginatedCollectionStillIgnoresSearchAndSort(): void
    {
        $this->dependencies[PatientRepository::class]->expects(self::once())->method('findBy')
            ->with(['deletedAt' => null], ['nom' => 'ASC'])->willReturn([]);
        $this->dependencies[FactureRepository::class]->expects(self::never())->method('sumUnpaidResteByPatientIds');

        self::assertSame([], $this->service->listPatientsCollection(query: 'ignored', sortField: 'prenom', sortOrder: 'desc'));
    }

    public static function restrictedReadCases(): iterable
    {
        foreach (['listPatientsByMedecin', 'getOverviewStats'] as $method) {
            yield $method . '-anonymous' => [$method, null, ['error' => 'Utilisateur non authentifié', 'status' => 401]];
            yield $method . '-no-employee' => [$method, new \stdClass(), ['error' => 'Aucun employé associé', 'status' => 404]];
        }
    }

    /** @dataProvider restrictedReadCases */
    public function testRestrictedReadErrorsAreUnchanged(string $method, ?object $user, array $expected): void
    {
        $this->dependencies[PatientRepository::class]->expects(self::never())->method('findBy');
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('createQueryBuilder');
        $this->dependencies[EmployeRepository::class]->expects($user ? self::once() : self::never())
            ->method('findOneBy')->willReturn(null);

        $result = $method === 'getOverviewStats'
            ? $this->service->getOverviewStats($user, true)
            : $this->service->$method($user);

        self::assertSame($expected, $result);
    }

    public function testSettingCanBroadenDoctorCollectionWithoutResolvingAnEmployee(): void
    {
        $this->dependencies[GlobalSettingsService::class]->method('isMedecinCreatePatientAndConsultationAllowed')->willReturn(true);
        $this->dependencies[EmployeRepository::class]->expects(self::never())->method('findOneBy');
        $this->dependencies[PatientRepository::class]->expects(self::once())->method('findBy')
            ->with(['deletedAt' => null], ['nom' => 'ASC'])->willReturn([]);

        self::assertSame([], $this->service->listPatientsByMedecin(null));
    }

    public function testDoctorPaginationKeepsItsOwnCacheScopeAndNormalizesInvalidSort(): void
    {
        $user = new User();
        $doctor = new Employe();
        (new \ReflectionProperty(Employe::class, 'id'))->setValue($doctor, 11);
        $this->dependencies[EmployeRepository::class]->method('findOneBy')->with(['user' => $user])->willReturn($doctor);
        $this->dependencies[PatientRepository::class]->expects(self::once())->method('paginatePatientsByMedecin')
            ->with($doctor, 2, 1, null, null, 'asc')->willReturn(['items' => [], 'total' => 0]);
        $this->dependencies[PatientRepository::class]->expects(self::never())->method('paginatePatients');

        self::assertSame([
            'items' => [], 'total' => 0, 'page' => 2, 'limit' => 1, 'sortField' => null, 'sortOrder' => 'asc',
        ], $this->service->listPatientsByMedecinPaginated($user, 2, 0, sortOrder: 'invalid'));
        self::assertTrue($this->cache->hasItem('patients.collection.medecin.11.2.1.' . sha1('') . '.default.asc'));
    }

    public function testDeletedCollectionPreservesDeletionTimestampAndIsNotCached(): void
    {
        $patient = $this->patient()->setDeletedAt(new \DateTimeImmutable('2026-10-01 12:34:56'));
        $this->dependencies[PatientRepository::class]->expects(self::exactly(2))->method('paginateDeletedPatients')
            ->with(1, 100, 'Doe')->willReturn(['items' => [$patient], 'total' => 1]);
        $this->dependencies[FactureRepository::class]->method('sumUnpaidResteByPatientIds')->willReturn([]);

        $result = $this->service->listDeletedPatientsPaginated(0, 101, 'Doe');

        self::assertSame('2026-10-01 12:34:56', $result['items'][0]['deletedAt']);
        self::assertSame(0, $result['items'][0]['impayees']);
        self::assertSame(1, $result['page']);
        self::assertSame(100, $result['limit']);
        self::assertSame($result, $this->service->listDeletedPatientsPaginated(0, 101, 'Doe'));
    }

    public static function missingPatientCases(): iterable
    {
        foreach (['getPatientPortalAccountData', 'createPatientPortalAccount', 'resetPatientPortalPassword', 'togglePatientPortalAccount'] as $method) {
            yield $method . '-missing' => [$method, false];
            yield $method . '-deleted' => [$method, true];
        }
    }

    /** @dataProvider missingPatientCases */
    public function testPortalOperationsRejectMissingAndDeletedPatients(string $method, bool $deleted): void
    {
        $patient = $deleted ? $this->patient()->setDeletedAt(new \DateTimeImmutable()) : null;
        $this->dependencies[PatientRepository::class]->method('find')->with(7)->willReturn($patient);
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('persist');
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('flush');

        self::assertSame(['error' => 'Patient introuvable', 'status' => 404], $this->service->$method(7, true));
    }

    public function testPortalCreationTransliteratesAndStartsCollisionSuffixAtTwo(): void
    {
        $patient = $this->patient()->setNom('Dôe')->setPrenom('Jane');
        $this->dependencies[PatientRepository::class]->method('find')->willReturn($patient);
        $base = 'doejane' . ($patient->getAge() ?? 0) . '20260102';
        $attempts = [];
        $this->dependencies[UserRepository::class]->expects(self::exactly(3))->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use (&$attempts, $base): ?User {
                $attempts[] = $criteria['username'];
                return $criteria['username'] === $base . '3' ? null : new User();
            });
        $this->dependencies[UserPasswordHasherInterface::class]->expects(self::once())->method('hashPassword')
            ->with(self::isInstanceOf(User::class), '123')->willReturn('hashed-password');
        $this->dependencies[EntityManagerInterface::class]->expects(self::exactly(2))->method('persist');
        $this->dependencies[EntityManagerInterface::class]->expects(self::once())->method('flush');

        $result = $this->service->createPatientPortalAccount(7);

        self::assertSame([$base, $base . '2', $base . '3'], $attempts);
        self::assertSame('hashed-password', $patient->getPortalUser()->getPassword());
        self::assertSame([
            'success' => true,
            'message' => 'Compte patient cree avec succes.',
            'account' => [
                'id' => null, 'username' => $base . '3', 'active' => true,
                'roles' => ['ROLE_PATIENT', 'ROLE_USER'], 'defaultPassword' => '123',
            ],
        ], $result);
    }

    public function testExistingPortalAccountCreationIsIdempotent(): void
    {
        $patient = $this->patient()->setPortalUser((new User())->setUsername('existing')->setActive(false));
        $this->dependencies[PatientRepository::class]->method('find')->willReturn($patient);
        $this->dependencies[UserRepository::class]->expects(self::never())->method('findOneBy');
        $this->dependencies[UserPasswordHasherInterface::class]->expects(self::never())->method('hashPassword');
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('persist');
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('flush');

        $result = $this->service->createPatientPortalAccount(7);

        self::assertSame('Compte utilisateur deja lie au patient.', $result['message']);
        self::assertFalse($result['account']['active']);
        self::assertSame($result['account'], $this->service->getPatientPortalAccountData(7)['account']);
    }

    public function testPatientWithoutPortalAccountHasNullAccountAndCompatibleMutationErrors(): void
    {
        $this->dependencies[PatientRepository::class]->method('find')->willReturn($this->patient());
        $this->dependencies[EntityManagerInterface::class]->expects(self::never())->method('flush');

        self::assertSame(['success' => true, 'account' => null], $this->service->getPatientPortalAccountData(7));
        $error = ['error' => 'Aucun compte utilisateur lie a ce patient', 'status' => 404];
        self::assertSame($error, $this->service->resetPatientPortalPassword(7));
        self::assertSame($error, $this->service->togglePatientPortalAccount(7, false));
    }

    private function queryBuilder(array $rows = [], ?string $scalar = null): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($rows);
        $query->method('getArrayResult')->willReturn($rows);
        $query->method('getSingleScalarResult')->willReturn($scalar);
        $builder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'leftJoin', 'innerJoin', 'andWhere', 'from', 'setParameter', 'orderBy', 'groupBy'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('getQuery')->willReturn($query);

        return $builder;
    }

    public static function bulkPortalCases(): iterable
    {
        yield 'empty' => [0];
        yield 'multiple' => [2];
    }

    /** @dataProvider bulkPortalCases */
    public function testBulkPortalCreationFlushesAndClearsCacheOnlyWhenAccountsWereCreated(int $count): void
    {
        $patients = $count ? [$this->patient(7), $this->patient(8)] : [];
        $builder = $this->queryBuilder([...$patients, new \stdClass()]);
        $this->dependencies[PatientRepository::class]->method('createQueryBuilder')->with('p')->willReturn($builder);
        $this->dependencies[UserRepository::class]->method('findOneBy')->willReturn(null);
        $this->dependencies[UserPasswordHasherInterface::class]->expects(self::exactly($count))->method('hashPassword')->willReturn('hash');
        $this->dependencies[EntityManagerInterface::class]->expects(self::exactly($count * 2))->method('persist');
        $this->dependencies[EntityManagerInterface::class]->expects($count ? self::once() : self::never())->method('flush');
        $this->cache->get('patients.sentinel', static fn () => 'cached');

        self::assertSame([
            'success' => true, 'createdCount' => $count,
            'message' => sprintf('%d compte(s) patient créé(s).', $count),
        ], $this->service->createMissingPatientPortalAccounts());
        self::assertSame($count === 0, $this->cache->hasItem('patients.sentinel'));
        foreach ($patients as $patient) {
            self::assertInstanceOf(User::class, $patient->getPortalUser());
        }
    }

    public static function overviewCases(): iterable
    {
        yield 'global' => [false, false];
        yield 'doctor' => [true, false];
        yield 'doctor-broadened-by-setting' => [true, true];
    }

    /** @dataProvider overviewCases */
    public function testOverviewPreservesCountersReferralsAndDoctorScope(bool $doctorOnly, bool $broaden): void
    {
        $user = new User();
        $doctor = new Employe();
        $restricted = $doctorOnly && !$broaden;
        $this->dependencies[GlobalSettingsService::class]->method('isMedecinCreatePatientAndConsultationAllowed')->willReturn($broaden);
        $this->dependencies[EmployeRepository::class]->expects($restricted ? self::once() : self::never())
            ->method('findOneBy')->with(['user' => $user])->willReturn($doctor);
        $total = $this->queryBuilder(scalar: '12');
        $total->expects($restricted ? self::once() : self::never())->method('setParameter')->with('medecin', $doctor);
        $consultations = $this->queryBuilder(scalar: '3');
        $appointments = $this->queryBuilder(scalar: '4');
        $newPatients = $this->queryBuilder(scalar: '5');
        $referrals = $this->queryBuilder([
            ['source' => '', 'cnt' => '2'], ['source' => 'Internet', 'cnt' => '10'],
        ]);
        $this->dependencies[PatientRepository::class]->expects(self::exactly(3))->method('createQueryBuilder')
            ->with('p')->willReturnOnConsecutiveCalls($total, $newPatients, $referrals);
        $this->dependencies[ConsultationRepository::class]->method('createQueryBuilder')->with('c')->willReturn($consultations);
        $this->dependencies[EntityManagerInterface::class]->method('createQueryBuilder')->willReturn($appointments);

        self::assertSame([
            'totalPatients' => 12, 'consultationsToday' => 3, 'upcomingAppointments' => 4,
            'newPatientsThisMonth' => 5,
            'referrals' => [['source' => 'Non renseigné', 'count' => 2], ['source' => 'Internet', 'count' => 10]],
        ], $this->service->getOverviewStats($user, $doctorOnly));
    }

    public function testPasswordResetAndTogglePreserveMessagesPersistenceAndCacheBehavior(): void
    {
        $user = (new User())->setUsername('portal')->setActive(true)->setPassword('old');
        $patient = $this->patient()->setPortalUser($user);
        $this->dependencies[PatientRepository::class]->method('find')->willReturn($patient);
        $this->dependencies[UserPasswordHasherInterface::class]->expects(self::once())->method('hashPassword')
            ->with($user, '123')->willReturn('reset-hash');
        $this->dependencies[EntityManagerInterface::class]->expects(self::exactly(3))->method('persist')->with($user);
        $this->dependencies[EntityManagerInterface::class]->expects(self::exactly(3))->method('flush');
        $this->cache->get('patients.sentinel', static fn () => 'cached');

        self::assertSame('Mot de passe reinitialise a 123.', $this->service->resetPatientPortalPassword(7)['message']);
        self::assertSame('reset-hash', $user->getPassword());
        $result = $this->service->togglePatientPortalAccount(7, false);
        self::assertSame('Compte desactive.', $result['message']);
        self::assertFalse($result['account']['active']);
        self::assertSame('Compte active.', $this->service->togglePatientPortalAccount(7, true)['message']);
        self::assertTrue($this->cache->hasItem('patients.sentinel'));
    }
}
