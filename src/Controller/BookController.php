<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Category;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    public function __construct(
        private BookRepository $bookRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'book_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $books = $this->bookRepository->findAll();

        return $this->json(array_map(
            fn (Book $book): array => $this->formatBook($book),
            $books
        ));
    }

    #[Route('/{id}', name: 'book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Book $book): JsonResponse
    {
        return $this->json($this->formatBook($book));
    }

    #[Route('', name: 'book_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validateBookData($data);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        if ($this->bookRepository->findOneBy(['isbn' => $data['isbn']])) {
            return $this->json(['message' => 'Cet ISBN est deja utilise'], Response::HTTP_BAD_REQUEST);
        }

        $category = $this->findCategory($data);
        if (!$category) {
            return $this->json(['message' => 'Categorie introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $book = new Book();
        $this->fillBook($book, $data, $category);

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $this->json($this->formatBook($book), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'book_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(Book $book, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $mergedData = array_merge($this->bookToData($book), $data);
        $error = $this->validateBookData($mergedData);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        $existingBook = $this->bookRepository->findOneBy(['isbn' => $mergedData['isbn']]);
        if ($existingBook && $existingBook->getId() !== $book->getId()) {
            return $this->json(['message' => 'Cet ISBN est deja utilise'], Response::HTTP_BAD_REQUEST);
        }

        $category = $this->findCategory($mergedData);
        if (!$category) {
            return $this->json(['message' => 'Categorie introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $this->fillBook($book, $mergedData, $category);
        $this->entityManager->flush();

        return $this->json($this->formatBook($book));
    }

    #[Route('/{id}', name: 'book_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Book $book): JsonResponse
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validateBookData(array $data): ?string
    {
        $requiredFields = ['title', 'author', 'isbn', 'totalCopies', 'availableCopies', 'categoryId'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                return sprintf('Le champ "%s" est obligatoire', $field);
            }
        }

        if ((int) $data['totalCopies'] < 0 || (int) $data['availableCopies'] < 0) {
            return 'Les nombres de copies doivent etre positifs';
        }

        if ((int) $data['availableCopies'] > (int) $data['totalCopies']) {
            return 'Le nombre de copies disponibles ne peut pas depasser le total';
        }

        if (!empty($data['publishedAt']) && !$this->isValidDate((string) $data['publishedAt'])) {
            return 'La date de publication est invalide';
        }

        return null;
    }

    private function findCategory(array $data): ?Category
    {
        return $this->categoryRepository->find((int) $data['categoryId']);
    }

    private function fillBook(Book $book, array $data, Category $category): void
    {
        $book
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null)
            ->setAuthor($data['author'])
            ->setIsbn($data['isbn'])
            ->setTotalCopies((int) $data['totalCopies'])
            ->setAvailableCopies((int) $data['availableCopies'])
            ->setPublishedAt($this->parseDate($data['publishedAt'] ?? null))
            ->setImage($data['image'] ?? null)
            ->setCategory($category);
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        return new \DateTimeImmutable($date);
    }

    private function isValidDate(string $date): bool
    {
        try {
            new \DateTimeImmutable($date);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function bookToData(Book $book): array
    {
        return [
            'title' => $book->getTitle(),
            'description' => $book->getDescription(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'totalCopies' => $book->getTotalCopies(),
            'availableCopies' => $book->getAvailableCopies(),
            'publishedAt' => $book->getPublishedAt()?->format('Y-m-d'),
            'image' => $book->getImage(),
            'categoryId' => $book->getCategory()?->getId(),
        ];
    }

    private function formatBook(Book $book): array
    {
        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'description' => $book->getDescription(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'totalCopies' => $book->getTotalCopies(),
            'availableCopies' => $book->getAvailableCopies(),
            'publishedAt' => $book->getPublishedAt()?->format('Y-m-d'),
            'image' => $book->getImage(),
            'category' => $this->formatCategory($book->getCategory()),
        ];
    }

    private function formatCategory(?Category $category): ?array
    {
        if (!$category) {
            return null;
        }

        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
        ];
    }
}
