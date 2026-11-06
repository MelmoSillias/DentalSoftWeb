<?php

namespace App\IdentityAccess\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Checks the login audience after password verification, before JWT creation. */
final class LoginUserChecker implements UserCheckerInterface
{
    public const STAFF_ROLES = ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'];

    public function __construct(
        private UserEnabledChecker $enabledChecker,
        private RequestStack $requestStack,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        $this->enabledChecker->checkPreAuth($user);
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $roles = $user->getRoles();
        $isStaff = [] !== array_intersect(self::STAFF_ROLES, $roles);
        $isPatientLogin = '/api/login_patient_check' === $this->requestStack->getCurrentRequest()?->getPathInfo();

        if ($isPatientLogin ? (!in_array('ROLE_PATIENT', $roles, true) || $isStaff) : !$isStaff) {
            throw new BadCredentialsException();
        }
    }
}
