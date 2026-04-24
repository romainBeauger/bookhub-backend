<?php

namespace App\Controller;

use App\Services\AuthService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Authentification')]
final class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
    ) {}

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Créer un compte utilisateur',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'email', 'mot_de_passe'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                    new OA\Property(property: 'mot_de_passe', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
        ]
    )]
    #[Security(name: null)]
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
