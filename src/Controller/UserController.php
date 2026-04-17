<?php

namespace App\Controller;

use App\Services\UserService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Profil utilisateur')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    #[Route('/me', name: 'user_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/me',
        summary: 'Récupérer le profil de l\'utilisateur connecté',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil retourné avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '0612345678'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2026-01-01'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
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
    #[OA\Patch(
        path: '/api/users/me',
        summary: 'Modifier le profil (nom, prénom, email)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '0612345678'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
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
    #[OA\Patch(
        path: '/api/users/me/password',
        summary: 'Changer le mot de passe',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', example: 'ancienMotDePasse'),
                    new OA\Property(property: 'new_password', type: 'string', example: 'nouveauMotDePasse'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe mis à jour'),
            new OA\Response(response: 400, description: 'Ancien mot de passe incorrect ou données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
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
