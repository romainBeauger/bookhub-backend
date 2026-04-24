<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Services\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthServiceTest extends TestCase
{
    // Ces propriétés contiendront nos "faux objets" (mocks)
    /** @var UserRepository&MockObject */
    private UserRepository $userRepository;

    /** @var UserPasswordHasherInterface&MockObject */
    private UserPasswordHasherInterface $passwordHasher;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private AuthService $authService;

    protected function setUp(): void
    {
        // createMock() crée un faux objet qui imite l'interface/classe
        // sans vraiment appeler la BDD ou le vrai hasheur
        $this->userRepository  = $this->createMock(UserRepository::class);
        $this->passwordHasher  = $this->createMock(UserPasswordHasherInterface::class);
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);

        $this->authService = new AuthService(
            $this->userRepository,
            $this->passwordHasher,
            $this->entityManager,
        );
    }

    // --- Tests de validation ---

    public function testValidationEchoueAvecChampsVides(): void
    {
        $error = $this->authService->validateRegistrationData([]);
        $this->assertSame('Les données ne peuvent pas être vides', $error);
    }

    public function testValidationEchoueAvecEmailInvalide(): void
    {
        $error = $this->authService->validateRegistrationData([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'pasunemail',
            'mot_de_passe' => 'secret123',
        ]);
        $this->assertSame('Format email invalide', $error);
    }

    public function testValidationEchoueAvecMotDePasseTropCourt(): void
    {
        $error = $this->authService->validateRegistrationData([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'jean@mail.com',
            'mot_de_passe' => '1234567', // 7 caractères, trop court
        ]);
        $this->assertSame('Le mot de passe doit faire au moins 8 caractères', $error);
    }

    public function testValidationEchoueAvecEmailDejaUtilise(): void
    {
        // On dit au mock : si findByEmail() est appelé, retourne un User existant
        $this->userRepository
            ->method('findByEmail')
            ->willReturn(new User());

        $error = $this->authService->validateRegistrationData([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'jean@mail.com',
            'mot_de_passe' => 'secret123',
        ]);
        $this->assertSame('Cet email est déjà utilisé', $error);
    }

    public function testValidationReussitAvecDonneesValides(): void
    {
        // Le mock retourne null = email pas encore utilisé
        $this->userRepository
            ->method('findByEmail')
            ->willReturn(null);

        $error = $this->authService->validateRegistrationData([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'jean@mail.com',
            'mot_de_passe' => 'secret123',
        ]);
        $this->assertNull($error); // null = pas d'erreur
    }

    // --- Test du hachage du mot de passe ---

    public function testRegisterUserHacheLeMotDePasse(): void
    {
        // On dit au mock : hashPassword() doit retourner cette chaîne hachée
        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('$2y$12$hashed_password_example');

        // persist() et flush() ne font rien (mocks), c'est OK
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->authService->registerUser([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'jean@mail.com',
            'mot_de_passe' => 'secret123',
        ]);

        // Le mot de passe stocké doit être la version hachée, pas "secret123"
        $this->assertNotSame('secret123', $user->getPassword());
        $this->assertSame('$2y$12$hashed_password_example', $user->getPassword());
    }

    // --- Test du rôle par défaut ---

    public function testRegisterUserAttribueRoleUserParDefaut(): void
    {
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $user = $this->authService->registerUser([
            'nom'         => 'Dupont',
            'prenom'      => 'Jean',
            'email'       => 'jean@mail.com',
            'mot_de_passe' => 'secret123',
        ]);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }
}
