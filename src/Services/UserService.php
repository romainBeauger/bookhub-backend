<?php

namespace App\Services;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class UserService
{
    private const ALLOWED_ROLES = [
        'ROLE_USER',
        'ROLE_LIBRARIAN',
        'ROLE_ADMIN',
    ];

    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        if (empty($data['nom']) && empty($data['prenom']) && empty($data['email']) && !array_key_exists('phone', $data)) {
            throw new \InvalidArgumentException('Au moins un champ est requis.');
        }

        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Format email invalide.');
            }

            $existing = $this->userRepository->findByEmail($data['email']);
            if ($existing && $existing->getId() !== $user->getId()) {
                throw new \InvalidArgumentException('Cet email est deja utilise.');
            }

            $user->setEmail($data['email']);
        }

        if (!empty($data['nom'])) {
            $user->setLastName($data['nom']);
        }

        if (!empty($data['prenom'])) {
            $user->setFirstName($data['prenom']);
        }

        if (array_key_exists('phone', $data)) {
            $user->setPhone($data['phone']);
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
            throw new \InvalidArgumentException('Le nouveau mot de passe doit faire au moins 8 caracteres.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $data['nouveau_mot_de_passe']));
        $this->entityManager->flush();
    }

    public function anonymize(User $user): void
    {
        $user->setFirstName('Utilisateur');
        $user->setLastName('Supprime');
        $user->setEmail('deleted_' . $user->getId() . '@bookhub.local');
        $user->setPassword('DELETED');
        $user->setPhone(null);
        $user->setIsActive(false);
        $user->setSuspendedUntil(null);

        $this->entityManager->flush();
    }

    /**
     * @return User[]
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAllOrderedByCreationDate();
    }

    public function createUserByAdmin(array $data): User
    {
        $this->validateAdminCreateData($data);

        $email = trim((string) $data['email']);
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException('Cet email est deja utilise.');
        }

        $user = new User();
        $user->setLastName(trim((string) $data['nom']));
        $user->setFirstName(trim((string) $data['prenom']));
        $user->setEmail($email);
        $user->setPhone($this->normalizePhone($data['phone'] ?? null));
        $user->setRoles($this->extractRoles($data, true));
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $data['mot_de_passe']));
        $user->setIsActive($this->extractIsActive($data, true));
        $user->setSuspendedUntil(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function updateUserByAdmin(User $user, array $data, ?User $actor = null): User
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Au moins un champ est requis.');
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Format email invalide.');
            }

            $existing = $this->userRepository->findByEmail($email);
            if ($existing && $existing->getId() !== $user->getId()) {
                throw new \InvalidArgumentException('Cet email est deja utilise.');
            }

            $user->setEmail($email);
        }

        if (array_key_exists('nom', $data)) {
            $nom = trim((string) $data['nom']);
            if ($nom === '') {
                throw new \InvalidArgumentException('Le nom est requis.');
            }

            $user->setLastName($nom);
        }

        if (array_key_exists('prenom', $data)) {
            $prenom = trim((string) $data['prenom']);
            if ($prenom === '') {
                throw new \InvalidArgumentException('Le prenom est requis.');
            }

            $user->setFirstName($prenom);
        }

        if (array_key_exists('phone', $data)) {
            $user->setPhone($this->normalizePhone($data['phone']));
        }

        if (array_key_exists('mot_de_passe', $data)) {
            $password = (string) $data['mot_de_passe'];
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('Le mot de passe doit faire au moins 8 caracteres.');
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }

        if (array_key_exists('role', $data) || array_key_exists('roles', $data)) {
            $roles = $this->extractRoles($data, false);
            if ($actor && $actor->getId() === $user->getId() && !in_array('ROLE_ADMIN', $roles, true)) {
                throw new \InvalidArgumentException('Un administrateur ne peut pas retirer son propre role admin.');
            }

            $user->setRoles($roles);
        }

        if (array_key_exists('isActive', $data) || array_key_exists('is_active', $data)) {
            $isActive = $this->extractIsActive($data, false);
            if ($actor && $actor->getId() === $user->getId() && $isActive === false) {
                throw new \InvalidArgumentException('Un administrateur ne peut pas se suspendre lui-meme.');
            }

            $user->setIsActive($isActive);
            if ($isActive) {
                $user->setSuspendedUntil(null);
            } else {
                $user->setSuspendedUntil(null);
            }
        }

        $this->entityManager->flush();

        return $user;
    }

    public function suspendUserByAdmin(User $user, array $data = [], ?User $actor = null): User
    {
        if ($actor && $actor->getId() === $user->getId()) {
            throw new \InvalidArgumentException('Un administrateur ne peut pas se suspendre lui-meme.');
        }

        $durationDays = $this->extractSuspensionDurationDays($data);
        if ($durationDays !== null) {
            $user->setIsActive(true);
            $user->setSuspendedUntil(new \DateTimeImmutable(sprintf('+%d days', $durationDays)));
        } else {
            $user->setIsActive(false);
            $user->setSuspendedUntil(null);
        }

        $this->entityManager->flush();

        return $user;
    }

    public function deleteUserByAdmin(User $user, ?User $actor = null): void
    {
        if ($actor && $actor->getId() === $user->getId()) {
            throw new \InvalidArgumentException('Un administrateur ne peut pas supprimer son propre compte via cet endpoint.');
        }

        $this->anonymize($user);
    }

    private function validateAdminCreateData(array $data): void
    {
        foreach (['nom', 'prenom', 'email', 'mot_de_passe'] as $field) {
            if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                throw new \InvalidArgumentException(sprintf('Le champ "%s" est obligatoire.', $field));
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Format email invalide.');
        }

        if (strlen((string) $data['mot_de_passe']) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit faire au moins 8 caracteres.');
        }
    }

    /**
     * @return string[]
     */
    private function extractRoles(array $data, bool $defaultToUser): array
    {
        $rolesInput = $data['roles'] ?? $data['role'] ?? null;

        if ($rolesInput === null) {
            if ($defaultToUser) {
                return ['ROLE_USER'];
            }

            throw new \InvalidArgumentException('Le role est obligatoire.');
        }

        $roles = is_array($rolesInput) ? $rolesInput : [$rolesInput];
        $normalizedRoles = [];

        foreach ($roles as $role) {
            $normalizedRole = strtoupper(trim((string) $role));
            if ($normalizedRole === '') {
                continue;
            }

            if (!in_array($normalizedRole, self::ALLOWED_ROLES, true)) {
                throw new \InvalidArgumentException(sprintf('Le role "%s" est invalide.', $normalizedRole));
            }

            $normalizedRoles[] = $normalizedRole;
        }

        if ($normalizedRoles === []) {
            throw new \InvalidArgumentException('Au moins un role valide est requis.');
        }

        return array_values(array_unique($normalizedRoles));
    }

    private function extractIsActive(array $data, bool $defaultValue): bool
    {
        if (!array_key_exists('isActive', $data) && !array_key_exists('is_active', $data)) {
            return $defaultValue;
        }

        $value = $data['isActive'] ?? $data['is_active'];

        if (!is_bool($value)) {
            throw new \InvalidArgumentException('Le champ isActive doit etre un booleen.');
        }

        return $value;
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalizedPhone = trim((string) $phone);

        return $normalizedPhone === '' ? null : $normalizedPhone;
    }

    private function extractSuspensionDurationDays(array $data): ?int
    {
        if (!array_key_exists('duree_jours', $data) || $data['duree_jours'] === null || $data['duree_jours'] === '') {
            return null;
        }

        if (filter_var($data['duree_jours'], FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Le champ duree_jours doit etre un entier.');
        }

        $durationDays = (int) $data['duree_jours'];
        if ($durationDays <= 0) {
            throw new \InvalidArgumentException('Le champ duree_jours doit etre superieur a 0.');
        }

        return $durationDays;
    }
}
