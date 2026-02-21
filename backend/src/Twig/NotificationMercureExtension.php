<?php

namespace App\Twig;

use App\Entity\User;
use App\Mercure\NotificationTopicGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationMercureExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationTopicGenerator $topicGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notifications_mercure_topic', [$this, 'getTopic']),
        ];
    }

    public function getTopic(): ?string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->topicGenerator->forUser($user);
    }
}
