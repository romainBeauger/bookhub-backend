<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\ActiveUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class ActiveUserCheckerTest extends TestCase
{
    public function testCheckPreAuthRejectsSuspendedUser(): void
    {
        $user = (new User())->setIsActive(false);

        $checker = new ActiveUserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est suspendu.');

        $checker->checkPreAuth($user);
    }

    public function testCheckPreAuthAllowsActiveUser(): void
    {
        $user = (new User())->setIsActive(true);

        $checker = new ActiveUserChecker();
        $checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testCheckPreAuthRejectsTemporarilySuspendedUser(): void
    {
        $user = (new User())
            ->setIsActive(true)
            ->setSuspendedUntil(new \DateTimeImmutable('+3 days'));

        $checker = new ActiveUserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est suspendu temporairement.');

        $checker->checkPreAuth($user);
    }

    public function testCheckPreAuthAllowsUserAfterSuspensionExpiration(): void
    {
        $user = (new User())
            ->setIsActive(true)
            ->setSuspendedUntil(new \DateTimeImmutable('-1 day'));

        $checker = new ActiveUserChecker();
        $checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }
}
