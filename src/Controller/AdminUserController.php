<?php

namespace App\Controller;

use App\Entity\User;
use App\Services\UserService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Administration utilisateurs')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Lister les utilisateurs pour l administration',
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function index(): JsonResponse
    {
        return $this->json(array_map(
            fn (User $user): array => $this->formatUser($user),
            $this->userService->getAllUsers()
        ));
    }

    #[Route('', name: 'admin_user_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/users',
        summary: 'Creer un utilisateur avec choix du role',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'email', 'mot_de_passe', 'role'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '0612345678'),
                    new OA\Property(property: 'mot_de_passe', type: 'string', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_LIBRARIAN'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur cree'),
            new OA\Response(response: 400, description: 'Donnees invalides'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userService->createUserByAdmin($data);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatUser($user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_user_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}',
        summary: 'Modifier un utilisateur et son role',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'mot_de_passe', type: 'string'),
                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_ADMIN'),
                    new OA\Property(property: 'isActive', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur mis a jour'),
            new OA\Response(response: 400, description: 'Donnees invalides'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function update(User $user, Request $request): JsonResponse
    {
        $user = $this->userService->syncUserStatus($user);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $admin */
        $admin = $this->getUser();

        try {
            $user = $this->userService->updateUserByAdmin($user, $data, $admin);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatUser($user));
    }

    #[Route('/{id}/suspend', name: 'admin_user_suspend', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}/suspend',
        summary: 'Suspendre un utilisateur, avec ou sans duree',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'duree_jours', type: 'integer', example: 7),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur suspendu'),
            new OA\Response(response: 400, description: 'Action invalide'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function suspend(User $user, Request $request): JsonResponse
    {
        $user = $this->userService->syncUserStatus($user);

        $data = json_decode($request->getContent(), true);
        if ($request->getContent() !== '' && !is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $admin */
        $admin = $this->getUser();

        try {
            $user = $this->userService->suspendUserByAdmin($user, $data ?? [], $admin);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatUser($user));
    }

    #[Route('/{id}/unsuspend', name: 'admin_user_unsuspend', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}/unsuspend',
        summary: 'Lever manuellement la suspension d un utilisateur',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Suspension retiree'),
            new OA\Response(response: 400, description: 'Action invalide'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function unsuspend(User $user): JsonResponse
    {
        $user = $this->userService->syncUserStatus($user);

        /** @var User|null $admin */
        $admin = $this->getUser();

        try {
            $user = $this->userService->unsuspendUserByAdmin($user, $admin);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatUser($user));
    }

    #[Route('/{id}', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/admin/users/{id}',
        summary: 'Supprimer un utilisateur par anonymisation',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur supprime'),
            new OA\Response(response: 400, description: 'Action invalide'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function delete(User $user): JsonResponse
    {
        $user = $this->userService->syncUserStatus($user);

        /** @var User|null $admin */
        $admin = $this->getUser();

        try {
            $this->userService->deleteUserByAdmin($user, $admin);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Utilisateur supprime.']);
    }

    private function formatUser(User $user): array
    {
        $user = $this->userService->syncUserStatus($user);

        return [
            'id' => $user->getId(),
            'nom' => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'suspendedUntil' => $user->getSuspendedUntil()?->format('Y-m-d H:i:s'),
            'isSuspended' => $user->getSuspendedUntil() !== null && $user->getSuspendedUntil() > new \DateTimeImmutable()
                ? true
                : $user->isActive() !== true,
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
