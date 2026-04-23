<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Services\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends TestCase
{
    /** @var UserRepository&MockObject */
    private UserRepository $userRepository;

    /** @var UserPasswordHasherInterface&MockObject */
    private UserPasswordHasherInterface $passwordHasher;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private UserService $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->userService = new UserService(
            $this->userRepository,
            $this->passwordHasher,
            $this->entityManager,
        );
    }

    public function testCreateUserByAdminUsesSelectedRole(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('admin-created@example.com')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), 'password123')
            ->willReturn('hashed-password');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->userService->createUserByAdmin([
            'nom' => 'Martin',
            'prenom' => 'Alice',
            'email' => 'admin-created@example.com',
            'mot_de_passe' => 'password123',
            'role' => 'ROLE_ADMIN',
        ]);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertSame('hashed-password', $user->getPassword());
        $this->assertTrue($user->isActive());
    }

    public function testUpdateUserByAdminCanChangeRoleAndStatus(): void
    {
        $user = (new User())
            ->setEmail('jean@example.com')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);
        $this->forceEntityId($user, 10);

        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('jean.updated@example.com')
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->userService->updateUserByAdmin($user, [
            'email' => 'jean.updated@example.com',
            'role' => 'ROLE_LIBRARIAN',
            'isActive' => false,
        ], $admin);

        $this->assertSame('jean.updated@example.com', $updated->getEmail());
        $this->assertContains('ROLE_LIBRARIAN', $updated->getRoles());
        $this->assertFalse($updated->isActive());
    }

    public function testSuspendUserByAdminRejectsSelfSuspend(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 3);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Un administrateur ne peut pas se suspendre lui-meme.');

        $this->userService->suspendUserByAdmin($admin, [], $admin);
    }

    public function testSuspendUserByAdminCanSetDuration(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);

        $user = (new User())
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);
        $this->forceEntityId($user, 6);

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->userService->suspendUserByAdmin($user, [
            'duree_jours' => 10,
        ], $admin);

        $this->assertTrue($updated->isActive());
        $this->assertNotNull($updated->getSuspendedUntil());
        $this->assertGreaterThan(new \DateTimeImmutable('+9 days'), $updated->getSuspendedUntil());
    }

    public function testSuspendUserByAdminRejectsInvalidDuration(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);

        $user = (new User())->setRoles(['ROLE_USER']);
        $this->forceEntityId($user, 6);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le champ duree_jours doit etre superieur a 0.');

        $this->userService->suspendUserByAdmin($user, [
            'duree_jours' => 0,
        ], $admin);
    }

    public function testDeleteUserByAdminAnonymizesUser(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);

        $user = (new User())
            ->setFirstName('Paul')
            ->setLastName('Durand')
            ->setEmail('paul@example.com')
            ->setPassword('hashed');
        $this->forceEntityId($user, 9);

        $this->entityManager->expects($this->once())->method('flush');

        $this->userService->deleteUserByAdmin($user, $admin);

        $this->assertSame('Utilisateur', $user->getFirstName());
        $this->assertSame('Supprime', $user->getLastName());
        $this->assertSame('deleted_9@bookhub.local', $user->getEmail());
        $this->assertFalse($user->isActive());
    }

    private function forceEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
