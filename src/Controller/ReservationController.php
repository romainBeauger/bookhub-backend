<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\ReservationRepository;
use App\Services\ReservationService;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations')]
#[OA\Tag(name: 'Reservations')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly ReservationRepository $reservationRepository,
        private readonly BookRepository $bookRepository,
    ) {}

    #[Route('', name: 'reservation_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/reservations',
        summary: 'Creer une reservation pour un livre',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['bookId'],
                properties: [
                    new OA\Property(property: 'bookId', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reservation creee avec succes'),
            new OA\Response(response: 400, description: 'Champ bookId manquant'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
            new OA\Response(response: 409, description: 'Reservation deja existante'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['bookId'])) {
            return $this->json(['error' => 'Le champ bookId est requis.'], 400);
        }

        $book = $this->bookRepository->find($data['bookId']);

        if (!$book) {
            return $this->json(['error' => 'Livre introuvable.'], 404);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();
            $reservation = $this->reservationService->createReservation($user, $book);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatReservation($reservation), 201);
    }

    #[Route('/me', name: 'reservation_my_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/reservations/me',
        summary: 'Lister mes reservations',
        responses: [
            new OA\Response(response: 200, description: 'Liste des reservations de l utilisateur'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function myReservations(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $reservations = $this->reservationService->getReservationsByUser($user);

        return $this->json(array_map(fn (Reservation $reservation) => $this->formatReservation($reservation), $reservations));
    }

    #[Route('', name: 'reservation_list_all', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Get(
        path: '/api/reservations',
        summary: 'Lister les reservations',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['PENDING', 'READY', 'VALIDATED', 'CANCELLED'])),
            new OA\Parameter(name: 'bookId', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userName', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des reservations'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function listAll(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $bookId = $request->query->get('bookId');
        $userName = trim((string) $request->query->get('userName', ''));

        if ($status !== null && !in_array($status, [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_READY,
            Reservation::STATUS_VALIDATED,
            Reservation::STATUS_CANCELLED,
        ], true)) {
            return $this->json(['error' => 'Le statut fourni est invalide.'], 400);
        }

        if ($bookId !== null && filter_var($bookId, FILTER_VALIDATE_INT) === false) {
            return $this->json(['error' => 'Le parametre bookId doit etre un entier.'], 400);
        }

        $reservations = $this->reservationService->getAllReservations(
            $status ?: null,
            $bookId !== null ? (int) $bookId : null,
            $userName !== '' ? $userName : null
        );

        return $this->json(array_map(fn (Reservation $reservation) => $this->formatReservation($reservation), $reservations));
    }

    #[Route('/{id}/ready', name: 'reservation_mark_ready', methods: ['PATCH'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Patch(
        path: '/api/reservations/{id}/ready',
        summary: 'Marquer une reservation comme prete',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reservation marquee comme prete'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Reservation introuvable'),
            new OA\Response(response: 409, description: 'Transition invalide'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function markReady(int $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);

        if (!$reservation) {
            return $this->json(['error' => 'Reservation introuvable.'], 404);
        }

        try {
            $reservation = $this->reservationService->markReady($reservation);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatReservation($reservation));
    }

    #[Route('/{id}/validate', name: 'reservation_validate', methods: ['PATCH'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Patch(
        path: '/api/reservations/{id}/validate',
        summary: 'Valider une reservation et creer l emprunt',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reservation validee et emprunt cree'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Reservation introuvable'),
            new OA\Response(response: 409, description: 'Transition invalide'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function validateReservation(int $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);

        if (!$reservation) {
            return $this->json(['error' => 'Reservation introuvable.'], 404);
        }

        try {
            $result = $this->reservationService->validateReservation($reservation);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json([
            'reservation' => $this->formatReservation($result['reservation']),
            'loan' => $this->formatLoan($result['loan']),
        ]);
    }

    #[Route('/{id}/cancel', name: 'reservation_cancel', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Patch(
        path: '/api/reservations/{id}/cancel',
        summary: 'Annuler une reservation',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reservation annulee'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Reservation introuvable'),
            new OA\Response(response: 409, description: 'Transition invalide'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $reservation = $this->reservationRepository->find($id);

        if (!$reservation) {
            return $this->json(['error' => 'Reservation introuvable.'], 404);
        }

        if (!$this->isReservationOwnedByUser($reservation, $currentUser) && !$this->isGranted('ROLE_LIBRARIAN')) {
            return $this->json(['error' => 'Acces refuse.'], 403);
        }

        try {
            $reservation = $this->reservationService->cancelReservation($reservation);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatReservation($reservation));
    }

    private function formatReservation(Reservation $reservation): array
    {
        $user = $reservation->getUser();
        $book = $reservation->getBook();

        return [
            'id' => $reservation->getId(),
            'reservationDate' => $reservation->getReservationDate()?->format('Y-m-d H:i:s'),
            'status' => $reservation->getStatus(),
            'queuePosition' => $reservation->getQueuePosition(),
            'userId' => $user?->getId(),
            'user' => $user ? [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ] : null,
            'bookId' => $book?->getId(),
            'book' => $book ? [
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'availableCopies' => $book->getAvailableCopies(),
            ] : null,
        ];
    }

    private function formatLoan(Loan $loan): array
    {
        return [
            'id' => $loan->getId(),
            'bookId' => $loan->getBook()->getId(),
            'bookTitle' => $loan->getBook()->getTitle(),
            'userId' => $loan->getUser()->getId(),
            'loanDate' => $loan->getLoanDate()?->format('Y-m-d'),
            'dueDate' => $loan->getDueDate()?->format('Y-m-d'),
            'status' => $loan->getStatus(),
        ];
    }

    private function isReservationOwnedByUser(Reservation $reservation, User $user): bool
    {
        $owner = $reservation->getUser();

        if (!$owner) {
            return false;
        }

        if ($owner->getId() !== null && $user->getId() !== null) {
            return $owner->getId() === $user->getId();
        }

        return $owner === $user;
    }
}
