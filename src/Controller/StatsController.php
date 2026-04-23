<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Entity\Loan;
use App\Repository\ReservationRepository;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
#[OA\Tag(name: 'Stats')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly LoanRepository $loanRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    #[Route('/loans', name: 'stats_loans', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Get(
        path: '/api/stats/loans',
        summary: 'Statistiques des emprunts (bibliothécaire)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques retournées avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'activeLoans', type: 'integer', example: 3),
                        new OA\Property(property: 'lateLoans', type: 'integer', example: 1),
                        new OA\Property(property: 'overdueList', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function loanStats(): JsonResponse
    {
        $overdueLoans = $this->loanRepository->findOverdueLoans();

        return $this->json([
            'activeLoans'  => $this->loanRepository->countActive(),
            'lateLoans'    => $this->loanRepository->countLate(),
            'overdueList'  => array_map(fn(Loan $l) => [
                'id'        => $l->getId(),
                'userId'    => $l->getUser()->getId(),
                'userName'  => $l->getUser()->getFirstName() . ' ' . $l->getUser()->getLastName(),
                'bookId'    => $l->getBook()->getId(),
                'bookTitle' => $l->getBook()->getTitle(),
                'dueDate'   => $l->getDueDate()->format('Y-m-d'),
                'loanDate'  => $l->getLoanDate()->format('Y-m-d'),
            ], $overdueLoans),
        ]);
    }

    #[Route('/catalogue', name: 'stats_catalogue', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/stats/catalogue',
        summary: 'Statistiques du catalogue pour le dashboard staff',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques du catalogue retournees avec succes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'totalBooks', type: 'integer', example: 125),
                        new OA\Property(property: 'totalReservations', type: 'integer', example: 32),
                        new OA\Property(property: 'currentReservations', type: 'integer', example: 8),
                        new OA\Property(property: 'pastReservations', type: 'integer', example: 24),
                        new OA\Property(property: 'topBorrowedBooks', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function catalogueStats(): JsonResponse
    {
        if (!$this->canViewCatalogueStats()) {
            return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'totalBooks' => $this->bookRepository->countAll(),
            'totalReservations' => $this->reservationRepository->countAllReservations(),
            'currentReservations' => $this->reservationRepository->countCurrentReservations(),
            'pastReservations' => $this->reservationRepository->countPastReservations(),
            'topBorrowedBooks' => $this->loanRepository->findMostBorrowedBooks(5),
        ]);
    }

    private function canViewCatalogueStats(): bool
    {
        return $this->isGranted('ROLE_LIBRARIAN') || $this->isGranted('ROLE_ADMIN');
    }
}
