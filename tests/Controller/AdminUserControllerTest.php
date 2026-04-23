<?php

namespace App\Tests\Controller;

use App\Controller\AdminUserController;
use App\Entity\User;
use App\Services\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AdminUserControllerTest extends TestCase
{
    /** @var UserService&MockObject */
    private UserService $userService;

    /** @var TokenStorageInterface&MockObject */
    private TokenStorageInterface $tokenStorage;

    private AdminUserController $controller;

    protected function setUp(): void
    {
        $this->userService = $this->createMock(UserService::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->controller = new AdminUserController($this->userService);

        $tokenStorage = $this->tokenStorage;
        $this->controller->setContainer(new class ($tokenStorage) implements ContainerInterface {
            public function __construct(
                private TokenStorageInterface $tokenStorage,
            ) {}

            public function get(string $id)
            {
                return match ($id) {
                    'security.token_storage' => $this->tokenStorage,
                    default => throw new \RuntimeException(sprintf('Unexpected service lookup: %s', $id)),
                };
            }

            public function has(string $id): bool
            {
                return $id === 'security.token_storage';
            }
        });
    }

    public function testIndexReturnsFormattedUsers(): void
    {
        $user = (new User())
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setEmail('jean@example.com')
            ->setRoles(['ROLE_LIBRARIAN'])
            ->setIsActive(true)
            ->setSuspendedUntil(new \DateTimeImmutable('+5 days'));
        $this->forceEntityId($user, 5);

        $this->userService
            ->expects($this->once())
            ->method('getAllUsers')
            ->willReturn([$user]);

        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent() ?: '', true);
        $this->assertSame(5, $payload[0]['id']);
        $this->assertContains('ROLE_LIBRARIAN', $payload[0]['roles']);
        $this->assertTrue($payload[0]['isActive']);
        $this->assertTrue($payload[0]['isSuspended']);
        $this->assertNotNull($payload[0]['suspendedUntil']);
    }

    public function testCreateDelegatesToServiceAndReturnsCreatedUser(): void
    {
        $createdUser = (new User())
            ->setFirstName('Alice')
            ->setLastName('Martin')
            ->setEmail('alice@example.com')
            ->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($createdUser, 8);

        $payload = [
            'nom' => 'Martin',
            'prenom' => 'Alice',
            'email' => 'alice@example.com',
            'mot_de_passe' => 'password123',
            'role' => 'ROLE_ADMIN',
        ];

        $this->userService
            ->expects($this->once())
            ->method('createUserByAdmin')
            ->with($payload)
            ->willReturn($createdUser);

        $request = new Request([], [], [], [], [], [], json_encode($payload));
        $response = $this->controller->create($request);

        $this->assertSame(201, $response->getStatusCode());
        $decoded = json_decode($response->getContent() ?: '', true);
        $this->assertSame('alice@example.com', $decoded['email']);
        $this->assertContains('ROLE_ADMIN', $decoded['roles']);
    }

    public function testUpdateCanChangeRole(): void
    {
        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);
        $this->mockAuthenticatedUser($admin);

        $user = (new User())
            ->setFirstName('Paul')
            ->setLastName('Durand')
            ->setEmail('paul@example.com')
            ->setRoles(['ROLE_LIBRARIAN']);
        $this->forceEntityId($user, 3);

        $payload = ['role' => 'ROLE_LIBRARIAN'];

        $this->userService
            ->expects($this->once())
            ->method('updateUserByAdmin')
            ->with($user, $payload, $admin)
            ->willReturn($user);

        $request = new Request([], [], [], [], [], [], json_encode($payload));
        $response = $this->controller->update($user, $request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent() ?: '', true);
        $this->assertContains('ROLE_LIBRARIAN', $decoded['roles']);
    }

    public function testSuspendAcceptsDurationPayload(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 2);
        $this->mockAuthenticatedUser($admin);

        $target = (new User())
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setSuspendedUntil(new \DateTimeImmutable('+7 days'));
        $this->forceEntityId($target, 9);

        $payload = ['duree_jours' => 7];

        $this->userService
            ->expects($this->once())
            ->method('suspendUserByAdmin')
            ->with($target, $payload, $admin)
            ->willReturn($target);

        $request = new Request([], [], [], [], [], [], json_encode($payload));
        $response = $this->controller->suspend($target, $request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent() ?: '', true);
        $this->assertTrue($decoded['isSuspended']);
        $this->assertNotNull($decoded['suspendedUntil']);
    }

    public function testSuspendReturnsBadRequestWhenServiceRejectsAction(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 2);
        $this->mockAuthenticatedUser($admin);

        $target = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($target, 2);

        $this->userService
            ->expects($this->once())
            ->method('suspendUserByAdmin')
            ->with($target, [], $admin)
            ->willThrowException(new \InvalidArgumentException('Un administrateur ne peut pas se suspendre lui-meme.'));

        $response = $this->controller->suspend($target, new Request([], [], [], [], [], [], ''));

        $this->assertSame(400, $response->getStatusCode());
        $decoded = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Un administrateur ne peut pas se suspendre lui-meme.', $decoded['message']);
    }

    public function testDeleteReturnsSuccessMessage(): void
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $this->forceEntityId($admin, 1);
        $this->mockAuthenticatedUser($admin);

        $target = (new User())->setRoles(['ROLE_USER']);
        $this->forceEntityId($target, 7);

        $this->userService
            ->expects($this->once())
            ->method('deleteUserByAdmin')
            ->with($target, $admin);

        $response = $this->controller->delete($target);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent() ?: '', true);
        $this->assertSame('Utilisateur supprime.', $decoded['message']);
    }

    private function mockAuthenticatedUser(User $user): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);
    }

    private function forceEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
