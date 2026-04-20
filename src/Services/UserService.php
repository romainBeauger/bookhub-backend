<?php

namespace App\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserService
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface      $entityManager,
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        if (empty($data['nom']) && empty($data['prenom']) && empty($data['email'])) {
            throw new \InvalidArgumentException('Au moins un champ est requis.');
        }

        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Format email invalide.');
            }

            $existing = $this->userRepository->findByEmail($data['email']);
            if ($existing && $existing->getId() !== $user->getId()) {
                throw new \InvalidArgumentException('Cet email est déjà utilisé.');
            }

            $user->setEmail($data['email']);
        }

        if (!empty($data['nom'])) {
            $user->setLastName($data['nom']);
        }

        if (!empty($data['prenom'])) {
            $user->setFirstName($data['prenom']);
        }

        $this->entityManager->flush();

        return $user;
    }

    public function updatePassword(User $user, array $data): void
    {
        if (empty($data['ancien_mot_de_passe']) || empty($data['nouveau_mot_de_passe'])) {
            throw new \InvalidArgumentException('Les deux champs mot de passe sont requis.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['ancien_mot_de_passe'])) {
            throw new \InvalidArgumentException('Ancien mot de passe incorrect.');
        }

        if (strlen($data['nouveau_mot_de_passe']) < 8) {
            throw new \InvalidArgumentException('Le nouveau mot de passe doit faire au moins 8 caractères.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $data['nouveau_mot_de_passe']));
        $this->entityManager->flush();
    }

    public function anonymize(User $user): void
    {
        $user->setFirstName('Utilisateur');
        $user->setLastName('Supprimé');
        $user->setEmail('deleted_' . $user->getId() . '@bookhub.local');
        $user->setPassword('DELETED');
        $user->setPhone(null);
        $user->setIsActive(false);

        $this->entityManager->flush();
    }
}
