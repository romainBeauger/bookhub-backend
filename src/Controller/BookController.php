<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Category;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/books')]
#[OA\Tag(name: 'Livres')]
final class BookController extends AbstractController
{
    public function __construct(
        private BookRepository $bookRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'book_index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/books',
        summary: 'Lister les livres avec filtres et pagination',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 12)),
            new OA\Parameter(name: 'q', in: 'query', description: 'Recherche par titre', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'author', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'categoryId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'available', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'publishedFrom', in: 'query', schema: new OA\Schema(type: 'string', example: '2000-01-01')),
            new OA\Parameter(name: 'publishedTo', in: 'query', schema: new OA\Schema(type: 'string', example: '2026-01-01')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc', 'random'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des livres'),
            new OA\Response(response: 400, description: 'Filtres invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 12)));
        $filters = [];

        $error = $this->validateSearchFilters($request);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        $filters = $this->extractSearchFilters($request);

        $books = $this->bookRepository->findPaginatedWithFilters($page, $limit, $filters);
        $total = $this->bookRepository->countFiltered($filters);
        $pages = max(1, (int) ceil($total / $limit));

        return $this->json([
            'data' => array_map(
                fn (Book $book): array => $this->formatBook($book),
                $books
            ),
            'filters' => $this->formatAppliedFilters($filters),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    #[Route('/{id}', name: 'book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/books/{id}',
        summary: 'Récupérer un livre par son ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Livre retourné'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function show(Book $book): JsonResponse
    {
        return $this->json($this->formatBook($book));
    }

    #[Route('', name: 'book_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/books',
        summary: 'Créer un livre',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'author', 'isbn', 'totalCopies', 'availableCopies', 'categoryId'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Le Petit Prince'),
                    new OA\Property(property: 'author', type: 'string', example: 'Antoine de Saint-Exupéry'),
                    new OA\Property(property: 'isbn', type: 'string', example: '978-2070612758'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'totalCopies', type: 'integer', example: 3),
                    new OA\Property(property: 'availableCopies', type: 'integer', example: 3),
                    new OA\Property(property: 'categoryId', type: 'integer', example: 1),
                    new OA\Property(property: 'publishedAt', type: 'string', example: '1943-04-06'),
                    new OA\Property(property: 'image', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Livre créé'),
            new OA\Response(response: 400, description: 'Données invalides ou ISBN déjà utilisé'),
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
    #[OA\Patch(
        path: '/api/books/{id}',
        summary: 'Modifier un livre (partiel ou complet)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'author', type: 'string'),
                    new OA\Property(property: 'isbn', type: 'string'),
                    new OA\Property(property: 'totalCopies', type: 'integer'),
                    new OA\Property(property: 'availableCopies', type: 'integer'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Livre mis à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
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
    #[OA\Delete(
        path: '/api/books/{id}',
        summary: 'Supprimer un livre',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Livre supprimé'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
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

    private function validateSearchFilters(Request $request): ?string
    {
        $publishedFrom = $request->query->get('publishedFrom');
        $publishedTo = $request->query->get('publishedTo');

        if ($publishedFrom !== null && $publishedFrom !== '' && !$this->isValidDate((string) $publishedFrom)) {
            return 'Le filtre "publishedFrom" est invalide';
        }

        if ($publishedTo !== null && $publishedTo !== '' && !$this->isValidDate((string) $publishedTo)) {
            return 'Le filtre "publishedTo" est invalide';
        }

        if ($publishedFrom && $publishedTo) {
            $from = new \DateTimeImmutable((string) $publishedFrom);
            $to = new \DateTimeImmutable((string) $publishedTo);

            if ($from > $to) {
                return 'Le filtre "publishedFrom" doit etre anterieur ou egal a "publishedTo"';
            }
        }

        $available = $request->query->get('available');
        if ($available !== null && $available !== '' && $this->parseBoolean($available) === null) {
            return 'Le filtre "available" doit etre true, false, 1 ou 0';
        }

        $categoryId = $request->query->get('categoryId');
        if ($categoryId !== null && $categoryId !== '' && filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
            return 'Le filtre "categoryId" doit etre un entier';
        }

        $sort = $request->query->get('sort');
        if ($sort !== null && $sort !== '' && !$this->isValidSortDirection((string) $sort)) {
            return 'Le filtre "sort" doit etre asc, desc ou random';
        }

        return null;
    }

    private function extractSearchFilters(Request $request): array
    {
        $filters = [];
        $q = trim((string) $request->query->get('q', ''));
        $author = trim((string) $request->query->get('author', ''));
        $categoryId = $request->query->get('categoryId');
        $publishedFrom = $request->query->get('publishedFrom');
        $publishedTo = $request->query->get('publishedTo');
        $available = $request->query->get('available');
        $sort = trim((string) $request->query->get('sort', ''));

        if ($q !== '') {
            $filters['q'] = $q;
        }

        if ($author !== '') {
            $filters['author'] = $author;
        }

        if ($categoryId !== null && $categoryId !== '') {
            $filters['categoryId'] = (int) $categoryId;
        }

        if ($publishedFrom !== null && $publishedFrom !== '') {
            $filters['publishedFrom'] = new \DateTimeImmutable((string) $publishedFrom);
        }

        if ($publishedTo !== null && $publishedTo !== '') {
            $filters['publishedTo'] = new \DateTimeImmutable((string) $publishedTo);
        }

        if ($available !== null && $available !== '') {
            $filters['available'] = $this->parseBoolean((string) $available);
        }

        if ($sort !== '') {
            $filters['sort'] = strtolower($sort);
        }

        return $filters;
    }

    private function formatAppliedFilters(array $filters): array
    {
        return [
            'q' => $filters['q'] ?? null,
            'author' => $filters['author'] ?? null,
            'categoryId' => $filters['categoryId'] ?? null,
            'available' => $filters['available'] ?? null,
            'publishedFrom' => isset($filters['publishedFrom']) ? $filters['publishedFrom']->format('Y-m-d') : null,
            'publishedTo' => isset($filters['publishedTo']) ? $filters['publishedTo']->format('Y-m-d') : null,
            'sort' => $filters['sort'] ?? 'random',
        ];
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

    private function parseBoolean(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => null,
        };
    }

    private function isValidSortDirection(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['asc', 'desc', 'random'], true);
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
