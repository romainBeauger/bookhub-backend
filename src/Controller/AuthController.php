<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api')]
final class AuthController extends AbstractController
{

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    )
    {}


    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['mot_de_passe']))
        {
            return $this->json(['message' => 'Les données ne peuvent pas être vide'], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Format email invalide'], 400);
        }

        if (strlen($data['mot_de_passe']) < 8) {
            return $this->json(['message' => 'Le mot de passe doit faire au moins 8 caractères'], 400);
        }

        $existingUser = $this->userRepository->findByEmail($data['email']);

        if ($existingUser) {
            return $this->json(['message' => 'Cet email est déjà utilisé'], 400);
        }

        $user = new User();
        $user->setLastName($data['nom']);
        $user->setFirstName($data['prenom']);
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['mot_de_passe']);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'nom' => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email' => $user->getEmail(),
        ], 201);

    }
}
