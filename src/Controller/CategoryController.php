<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'category_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $categories = $this->categoryRepository->findAll();

        return $this->json(array_map(
            fn (Category $category): array => $this->formatCategory($category),
            $categories
        ));
    }

    #[Route('/{id}', name: 'category_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Category $category): JsonResponse
    {
        return $this->json($this->formatCategory($category, true));
    }

    #[Route('', name: 'category_create', methods: ['POST'])]
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
