<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Avis')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly \App\Repository\LoanRepository $loanRepository,
    ) {}

    #[Route('/api/reviews', name: 'review_list_all', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/reviews',
        summary: 'Lister toutes les reviews pour le dashboard staff',
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['all', 'pending', 'confirmed'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des reviews'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function listAll(Request $request): JsonResponse
    {
        if (!$this->canModerateReviews()) {
            return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $status = strtolower(trim((string) $request->query->get('status', 'all')));
        if (!in_array($status, ['all', 'pending', 'confirmed'], true)) {
            return $this->json(['message' => 'Le filtre "status" doit etre all, pending ou confirmed'], Response::HTTP_BAD_REQUEST);
        }

        $reviews = $this->reviewRepository->findBy([], ['createdAt' => 'DESC']);

        $totalReviews = count($reviews);
        $pendingReviews = count(array_filter(
            $reviews,
            fn (Review $review): bool => !$review->isModerated()
        ));
        $confirmedReviews = count(array_filter(
            $reviews,
            fn (Review $review): bool => $review->isModerated()
        ));

        $filteredReviews = match ($status) {
            'pending' => array_values(array_filter(
                $reviews,
                fn (Review $review): bool => !$review->isModerated()
            )),
            'confirmed' => array_values(array_filter(
                $reviews,
                fn (Review $review): bool => $review->isModerated()
            )),
            default => $reviews,
        };

        return $this->json([
            'stats' => [
                'total' => $totalReviews,
                'pending' => $pendingReviews,
                'confirmed' => $confirmedReviews,
            ],
            'filter' => [
                'status' => $status,
            ],
            'data' => array_map(
                fn (Review $review): array => $this->formatReview($review),
                $filteredReviews
            ),
        ]);
    }

    #[Route('/api/books/{id}/reviews', name: 'review_list_by_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/books/{id}/reviews',
        summary: 'Lister les avis d un livre',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function listByBook(Book $book): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $reviews = $this->reviewRepository->findBy(
            ['book' => $book],
            ['createdAt' => 'DESC']
        );

        if (!$this->canModerateReviews()) {
            $reviews = array_values(array_filter(
                $reviews,
                fn (Review $review): bool => $review->isModerated() || $review->getUser()?->getId() === $currentUser->getId()
            ));
        }

        return $this->json(array_map(
            fn (Review $review): array => $this->formatReview($review),
            $reviews
        ));
    }

    #[Route('/api/reviews/me', name: 'review_my_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/reviews/me',
        summary: 'Lister les avis de l utilisateur connecte',
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function myReviews(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $reviews = $this->reviewRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->json(array_map(
            fn (Review $review): array => $this->formatReview($review),
            $reviews
        ));
    }

    #[Route('/api/books/{id}/reviews', name: 'review_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/books/{id}/reviews',
        summary: 'Creer un avis sur un livre',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', example: 5),
                    new OA\Property(property: 'comment', type: 'string', example: 'Excellent livre'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Avis cree'),
            new OA\Response(response: 400, description: 'Donnees invalides'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Livre non emprunte par l utilisateur'),
            new OA\Response(response: 409, description: 'Avis deja existant pour ce livre'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function create(Book $book, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validateReviewData($data, false);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Vérifie que l'utilisateur a bien emprunté ce livre
        if (!$this->canModerateReviews() && !$this->loanRepository->hasUserBorrowedBook($user, $book)) {
            return $this->json(
                ['message' => 'Vous devez avoir emprunté ce livre pour pouvoir le noter'],
                Response::HTTP_FORBIDDEN
            );
        }

        $existingReview = $this->reviewRepository->findOneBy([
            'user' => $user,
            'book' => $book,
        ]);

        if ($existingReview) {
            return $this->json(['message' => 'Vous avez deja note ce livre'], Response::HTTP_CONFLICT);
        }

        $review = new Review();
        $review
            ->setUser($user)
            ->setBook($book)
            ->setRating((int) $data['rating'])
            ->setComment($this->normalizeComment($data['comment'] ?? null));

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $this->json($this->formatReview($review), Response::HTTP_CREATED);
    }

    #[Route('/api/reviews/{id}', name: 'review_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Patch(
        path: '/api/reviews/{id}',
        summary: 'Modifier un avis',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', example: 4),
                    new OA\Property(property: 'comment', type: 'string', example: 'Avis mis a jour'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis mis a jour'),
            new OA\Response(response: 400, description: 'Donnees invalides'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function update(Review $review, Request $request): JsonResponse
    {
        if (!$this->canManageReview($review)) {
            return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validateReviewData($data, true);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('rating', $data)) {
            $review->setRating((int) $data['rating']);
        }

        if (array_key_exists('comment', $data)) {
            $review->setComment($this->normalizeComment($data['comment']));
        }

        $review->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json($this->formatReview($review));
    }

    #[Route('/api/reviews/{id}/moderate', name: 'review_moderate', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Patch(
        path: '/api/reviews/{id}/moderate',
        summary: 'Moderer un avis',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['isModerated'],
                properties: [
                    new OA\Property(property: 'isModerated', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis modere'),
            new OA\Response(response: 400, description: 'Donnees invalides'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function moderate(Review $review, Request $request): JsonResponse
    {
        if (!$this->canModerateReviews()) {
            return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (!array_key_exists('isModerated', $data) || !is_bool($data['isModerated'])) {
            return $this->json(['message' => 'Le champ "isModerated" est obligatoire et doit etre un booleen'], Response::HTTP_BAD_REQUEST);
        }

        $review
            ->setIsModerated($data['isModerated'])
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json($this->formatReview($review));
    }

    #[Route('/api/reviews/{id}', name: 'review_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Delete(
        path: '/api/reviews/{id}',
        summary: 'Supprimer un avis',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Avis supprime'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function delete(Review $review): JsonResponse
    {
        if (!$this->canManageReview($review)) {
            return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($review);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validateReviewData(array $data, bool $partial): ?string
    {
        if (!$partial && !array_key_exists('rating', $data)) {
            return 'Le champ "rating" est obligatoire';
        }

        if (array_key_exists('rating', $data)) {
            if (filter_var($data['rating'], FILTER_VALIDATE_INT) === false) {
                return 'Le champ "rating" doit etre un entier';
            }

            $rating = (int) $data['rating'];
            if ($rating < 1 || $rating > 5) {
                return 'La note doit etre comprise entre 1 et 5';
            }
        }

        if (array_key_exists('comment', $data)) {
            if ($data['comment'] !== null && !is_string($data['comment'])) {
                return 'Le champ "comment" doit etre une chaine ou null';
            }

            if (is_string($data['comment']) && mb_strlen(trim($data['comment'])) > 1000) {
                return 'Le commentaire ne peut pas depasser 1000 caracteres';
            }
        }

        return null;
    }

    private function normalizeComment(mixed $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $comment = trim((string) $comment);

        return $comment === '' ? null : $comment;
    }

    private function canModerateReviews(): bool
    {
        return $this->isGranted('ROLE_LIBRARIAN') || $this->isGranted('ROLE_ADMIN');
    }

    private function canManageReview(Review $review): bool
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        return $this->canModerateReviews() || $review->getUser()?->getId() === $currentUser->getId();
    }

    private function formatReview(Review $review): array
    {
        return [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'createdAt' => $review->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $review->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'isModerated' => $review->isModerated(),
            'user' => [
                'id' => $review->getUser()?->getId(),
                'firstName' => $review->getUser()?->getFirstName(),
                'lastName' => $review->getUser()?->getLastName(),
            ],
            'book' => [
                'id' => $review->getBook()?->getId(),
                'title' => $review->getBook()?->getTitle(),
            ],
        ];
    }
}
