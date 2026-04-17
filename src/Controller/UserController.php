<?php

namespace App\Controller;

use App\Services\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    #[Route('/me', name: 'user_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json([
            'id'        => $user->getId(),
            'nom'       => $user->getLastName(),
            'prenom'    => $user->getFirstName(),
            'email'     => $user->getEmail(),
            'phone'     => $user->getPhone(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d'),
        ]);
    }

    #[Route('/me', name: 'user_update_profile', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $user = $this->userService->updateProfile($user, $data ?? []);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json([
            'id'     => $user->getId(),
            'nom'    => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email'  => $user->getEmail(),
            'phone'  => $user->getPhone(),
        ]);
    }

    #[Route('/me/password', name: 'user_update_password', methods: ['PATCH'])]
    public function updatePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $this->userService->updatePassword($user, $data ?? []);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['message' => 'Mot de passe mis à jour.']);
    }
}
