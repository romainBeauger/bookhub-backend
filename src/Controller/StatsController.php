<?php

namespace App\Controller;

use App\Repository\LoanRepository;
use App\Entity\Loan;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
#[OA\Tag(name: 'Stats')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly LoanRepository $loanRepository,
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
}
