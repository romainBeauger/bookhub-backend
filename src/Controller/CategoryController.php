<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
#[OA\Tag(name: 'Catégories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'category_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories',
        summary: 'Lister toutes les catégories',
        responses: [
            new OA\Response(response: 200, description: 'Liste des catégories'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function index(): JsonResponse
    {
        $categories = $this->categoryRepository->findAll();

        return $this->json(array_map(
            fn (Category $category): array => $this->formatCategory($category),
            $categories
        ));
    }

    #[Route('/{id}', name: 'category_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories/{id}',
        summary: 'Récupérer une catégorie avec ses livres',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Catégorie retournée avec ses livres'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Catégorie introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function show(Category $category): JsonResponse
    {
        return $this->json($this->formatCategory($category, true));
    }

    #[Route('', name: 'category_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/categories',
        summary: 'Créer une catégorie',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Roman'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Catégorie créée'),
            new OA\Response(response: 400, description: 'Données invalides ou catégorie déjà existante'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validateCategoryData($data);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        if ($this->categoryRepository->findOneBy(['name' => $data['name']])) {
            return $this->json(['message' => 'Cette categorie existe deja'], Response::HTTP_BAD_REQUEST);
        }

        $category = new Category();
        $category->setName($data['name']);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->json($this->formatCategory($category), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'category_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[OA\Patch(
        path: '/api/categories/{id}',
        summary: 'Modifier une catégorie',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Science-fiction'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Catégorie mise à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Catégorie introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function update(Category $category, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $mergedData = ['name' => $data['name'] ?? $category->getName()];
        $error = $this->validateCategoryData($mergedData);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        $existingCategory = $this->categoryRepository->findOneBy(['name' => $mergedData['name']]);
        if ($existingCategory && $existingCategory->getId() !== $category->getId()) {
            return $this->json(['message' => 'Cette categorie existe deja'], Response::HTTP_BAD_REQUEST);
        }

        $category->setName($mergedData['name']);
        $this->entityManager->flush();

        return $this->json($this->formatCategory($category));
    }

    #[Route('/{id}', name: 'category_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/categories/{id}',
        summary: 'Supprimer une catégorie',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Catégorie supprimée'),
            new OA\Response(response: 400, description: 'Catégorie contient des livres'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Catégorie introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function delete(Category $category): JsonResponse
    {
        if (!$category->getBooks()->isEmpty()) {
            return $this->json([
                'message' => 'Impossible de supprimer une categorie qui contient des livres',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validateCategoryData(array $data): ?string
    {
        if (empty($data['name'])) {
            return 'Le nom de la categorie est obligatoire';
        }

        if (strlen($data['name']) < 2 || strlen($data['name']) > 100) {
            return 'Le nom de la categorie doit contenir entre 2 et 100 caracteres';
        }

        return null;
    }

    private function formatCategory(Category $category, bool $withBooks = false): array
    {
        $data = [
            'id' => $category->getId(),
            'name' => $category->getName(),
        ];

        if ($withBooks) {
            $data['books'] = array_map(
                fn (Book $book): array => $this->formatBook($book),
                $category->getBooks()->toArray()
            );
        }

        return $data;
    }

    private function formatBook(Book $book): array
    {
        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'availableCopies' => $book->getAvailableCopies(),
            'totalCopies' => $book->getTotalCopies(),
        ];
    }
}
