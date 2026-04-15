<?php

namespace App\Controller;

use App\Services\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
    ) {}

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $error = $this->authService->validateRegistrationData($data ?? []);
        if ($error !== null) {
            return $this->json(['message' => $error], 400);
        }

        $user = $this->authService->registerUser($data);

        return $this->json([
            'id'     => $user->getId(),
            'nom'    => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email'  => $user->getEmail(),
        ], 201);
    }
}
