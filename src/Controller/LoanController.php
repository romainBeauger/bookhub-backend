<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Services\LoanService;
use App\Entity\Loan;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/loans')]
#[OA\Tag(name: 'Emprunts')]
class LoanController extends AbstractController
{
    public function __construct(
        private readonly LoanService    $loanService,
        private readonly BookRepository $bookRepository,
        private readonly LoanRepository $loanRepository,
    ) {}

    #[Route('', name: 'loan_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/loans',
        summary: 'Emprunter un livre',
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
            new OA\Response(
                response: 201,
                description: 'Emprunt créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'bookId', type: 'integer', example: 1),
                        new OA\Property(property: 'bookTitle', type: 'string', example: 'Le Petit Prince'),
                        new OA\Property(property: 'loanDate', type: 'string', example: '2026-04-17'),
                        new OA\Property(property: 'dueDate', type: 'string', example: '2026-05-01'),
                        new OA\Property(property: 'status', type: 'string', example: 'ACTIVE'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Champ bookId manquant'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
            new OA\Response(response: 409, description: 'Livre indisponible ou déjà emprunté'),
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
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $loan = $this->loanService->createLoan($user, $book);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json([
            'id'         => $loan->getId(),
            'bookId'     => $book->getId(),
            'bookTitle'  => $book->getTitle(),
            'loanDate'   => $loan->getLoanDate()->format('Y-m-d'),
            'dueDate'    => $loan->getDueDate()->format('Y-m-d'),
            'status'     => $loan->getStatus(),
        ], 201);
    }

    #[Route('/me', name: 'loan_my_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/loans/me',
        summary: 'Mes emprunts (utilisateur connecté)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des emprunts de l\'utilisateur',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'bookTitle', type: 'string', example: 'Le Petit Prince'),
                            new OA\Property(property: 'loanDate', type: 'string', example: '2026-04-17'),
                            new OA\Property(property: 'dueDate', type: 'string', example: '2026-05-01'),
                            new OA\Property(property: 'status', type: 'string', example: 'ACTIVE'),
                            new OA\Property(property: 'isLate', type: 'boolean', example: false),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function myLoans(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $loans = $this->loanService->getLoansByUser($user);

        return $this->json(array_map(fn($l) => $this->formatLoan($l), $loans));
    }

    #[Route('', name: 'loan_list_all', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Get(
        path: '/api/loans',
        summary: 'Liste tous les emprunts actifs (bibliothécaire)',
        parameters: [
            new OA\Parameter(name: 'is_late', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Filtrer par retard'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des emprunts actifs'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function listAll(Request $request): JsonResponse
    {
        $isLate = $request->query->has('is_late')
            ? filter_var($request->query->get('is_late'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $loans = $this->loanService->getAllActiveLoans($isLate);

        return $this->json(array_map(fn($l) => $this->formatLoan($l), $loans));
    }

    #[Route('/{id}/return', name: 'loan_return', methods: ['PATCH'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Patch(
        path: '/api/loans/{id}/return',
        summary: 'Enregistrer le retour d\'un livre (bibliothécaire)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Retour enregistré avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Emprunt introuvable'),
            new OA\Response(response: 409, description: 'Emprunt déjà retourné'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function returnLoan(int $id): JsonResponse
    {
        $loan = $this->loanRepository->find($id);

        if (!$loan) {
            return $this->json(['error' => 'Emprunt introuvable.'], 404);
        }

        try {
            $loan = $this->loanService->returnLoan($loan);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatLoan($loan));
    }

    private function formatLoan(Loan $loan): array
    {
        return [
            'id'         => $loan->getId(),
            'bookId'     => $loan->getBook()->getId(),
            'bookTitle'  => $loan->getBook()->getTitle(),
            'userId'     => $loan->getUser()->getId(),
            'loanDate'   => $loan->getLoanDate()->format('Y-m-d'),
            'dueDate'    => $loan->getDueDate()->format('Y-m-d'),
            'returnedAt' => $loan->getReturnedAt()?->format('Y-m-d'),
            'status'     => $loan->getStatus(),
            'isLate'     => $loan->isLate(),
        ];
    }
}
