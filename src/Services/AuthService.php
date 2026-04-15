<?php

namespace App\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Valide les données d'inscription.
     * Retourne null si OK, ou un message d'erreur si KO.
     */
    public function validateRegistrationData(array $data): ?string
    {
        if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['mot_de_passe'])) {
            return 'Les données ne peuvent pas être vides';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Format email invalide';
        }

        if (strlen($data['mot_de_passe']) < 8) {
            return 'Le mot de passe doit faire au moins 8 caractères';
        }

        if ($this->userRepository->findByEmail($data['email'])) {
            return 'Cet email est déjà utilisé';
        }

        return null;
    }

    /**
     * Crée et persiste un nouvel utilisateur.
     */
    public function registerUser(array $data): User
    {
        $user = new User();
        $user->setLastName($data['nom']);
        $user->setFirstName($data['prenom']);
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['mot_de_passe']);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
