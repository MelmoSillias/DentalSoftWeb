<?php

namespace App\Communication\Security;

use App\IdentityAccess\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class VitrineVoter extends Voter
{
    public const ACCESS = 'VITRINE_ACCESS';
    public const ROLES = ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'];

    protected function supports(string $attribute, mixed $subject): bool { return $attribute === self::ACCESS; }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        return $user instanceof User && $user->isActive() && array_intersect(self::ROLES, $user->getRoles()) !== [];
    }
}
