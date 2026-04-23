<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $now = new \DateTimeImmutable();
        $suspendedUntil = $user->getSuspendedUntil();

        if ($suspendedUntil !== null && $suspendedUntil > $now) {
            throw new CustomUserMessageAccountStatusException('Votre compte est suspendu temporairement.');
        }

        if ($user->isActive() !== true && $suspendedUntil === null) {
            throw new CustomUserMessageAccountStatusException('Votre compte est suspendu.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
