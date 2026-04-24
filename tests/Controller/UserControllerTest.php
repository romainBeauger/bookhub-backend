<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Services\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserControllerDouble extends UserController
{
    private ?\Symfony\Component\Security\Core\User\UserInterface $testUser = null;

    public function setTestUser(?\Symfony\Component\Security\Core\User\UserInterface $user): void
    {
        $this->testUser = $user;
    }

    protected function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
    {
        return $this->testUser;
    }
}

class UserControllerTest extends TestCase
{
    /** @var UserService&MockObject */
    private UserService $userService;

    private UserControllerDouble $controller;

    protected function setUp(): void
    {
        $this->userService = $this->createMock(UserService::class);

        $this->controller = new UserControllerDouble($this->userService);

        $this->controller->setContainer(new class implements ContainerInterface {
            public function get(string $id) { throw new \RuntimeException("Service inattendu : $id"); }
            public function has(string $id): bool { return false; }
        });
    }

    // Vérifie que GET /api/users/me ne retourne pas le champ password dans la réponse
    public function testGetProfileReturnsNoPassword(): void
    {
        $user = new User();
        $user->setFirstName('Jean');
        $user->setLastName('Dupont');
        $user->setEmail('jean@example.com');
        $user->setPassword('$2y$12$hashedpassword');

        $this->controller->setTestUser($user);

        $response = $this->controller->me();

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertSame('jean@example.com', $payload['email']);
    }

    // Vérifie que PATCH /api/users/me appelle updateProfile() et retourne les nouvelles données
    public function testPatchProfileUpdatesCorrectly(): void
    {
        $user = new User();
        $user->setFirstName('Jean');
        $user->setLastName('Dupont');
        $user->setEmail('jean@example.com');

        $updatedUser = new User();
        $updatedUser->setFirstName('Pierre');
        $updatedUser->setLastName('Martin');
        $updatedUser->setEmail('pierre@example.com');

        $this->controller->setTestUser($user);

        $this->userService
            ->expects($this->once())
            ->method('updateProfile')
            ->with($user, ['prenom' => 'Pierre', 'nom' => 'Martin', 'email' => 'pierre@example.com'])
            ->willReturn($updatedUser);

        $request = new Request([], [], [], [], [], [], json_encode([
            'prenom' => 'Pierre',
            'nom'    => 'Martin',
            'email'  => 'pierre@example.com',
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->updateProfile($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Pierre', $payload['prenom']);
        $this->assertSame('pierre@example.com', $payload['email']);
    }

    // Vérifie que PATCH /api/users/me/password retourne 400 si l'ancien mot de passe est absent ou incorrect
    public function testChangePasswordRequiresOldPassword(): void
    {
        $user = new User();
        $this->controller->setTestUser($user);

        $this->userService
            ->method('updatePassword')
            ->willThrowException(new \InvalidArgumentException('L\'ancien mot de passe est requis.'));

        $request = new Request([], [], [], [], [], [], json_encode([
            'nouveau_mot_de_passe' => 'nouveauSecret123',
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->updatePassword($request);

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $payload);
    }

}
