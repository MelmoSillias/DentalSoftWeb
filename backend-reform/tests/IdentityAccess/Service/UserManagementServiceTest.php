<?php

namespace App\Tests\IdentityAccess\Service;

use App\Communication\Service\NotificationRecipientResolver;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserRepository;
use App\IdentityAccess\Service\UserManagementService;
use App\Patient\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementServiceTest extends TestCase
{
    public static function accountCases(): iterable
    {
        foreach (['createUser', 'updateUser'] as $method) {
            foreach ([
                [null, 'Admin', true],
                [null, 'Receptionniste', true],
                [null, 'Medecin', false],
                ['Medecin', 'Medecin', true],
                ['Admin', 'Admin', true],
                ['Receptionniste', 'Receptionniste', true],
                ['Medecin', 'Admin', false],
                ['Receptionniste', 'Medecin', false],
                ['Admin', 'Receptionniste', false],
                ['Infirmier', 'Receptionniste', false],
                ['Autre', 'Admin', false],
            ] as [$type, $role, $allowed]) {
                yield $method . '-' . ($type ?? 'sans-employe') . '-' . $role => [$method, $type, $role, $allowed];
            }
        }
    }

    /** @dataProvider accountCases */
    public function testAccountRoleMustMatchEligibleEmployee(string $method, ?string $type, string $role, bool $allowed): void
    {
        $user = (new User())->setUsername('existing')->setRoles(['ROLE_ADMIN']);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 1);
        $employee = $type ? (new Employe())->setType($type) : null;
        if ($employee) {
            (new \ReflectionProperty(Employe::class, 'id'))->setValue($employee, 7);
        }
        $users = $this->createMock(UserRepository::class);
        $users->method('find')->willReturn($user);
        $employees = $this->createMock(EmployeRepository::class);
        $employees->method('find')->willReturn($employee);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($allowed ? self::once() : self::never())->method('flush');
        if (!$allowed) $manager->expects(self::never())->method('persist');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');
        $recipients = new NotificationRecipientResolver($users);
        $service = new UserManagementService(
            $users, $employees, $this->createMock(PatientRepository::class), $manager, $hasher,
            $recipients, $this->createMock(EventDispatcherInterface::class)
        );

        $result = $service->$method(['user_id' => 1, 'username' => 'test', 'role' => $role, 'employee_id' => $employee ? 7 : null]);

        if ($allowed) {
            self::assertTrue($result['success']);
        } else {
            self::assertSame(400, $result['status']);
            self::assertArrayHasKey('error', $result);
            self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        }
        if ($employee) self::assertSame($type, $employee->getType());
    }
}
