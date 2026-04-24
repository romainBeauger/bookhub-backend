<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Services\LoanService;
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
        private readonly LoanService $loanService,
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
            new OA\Response(response: 201, description: 'Emprunt cree avec succes'),
            new OA\Response(response: 400, description: 'Champ bookId manquant'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 404, description: 'Livre introuvable'),
            new OA\Response(response: 409, description: 'Livre indisponible ou deja emprunte'),
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
            $loan = $this->loanService->createLoan($user, $book);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatLoan($loan), 201);
    }

    #[Route('/me', name: 'loan_my_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/loans/me',
        summary: 'Mes emprunts',
        responses: [
            new OA\Response(response: 200, description: 'Liste des emprunts de l utilisateur'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function myLoans(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $loans = $this->loanService->getLoansByUser($user);

        return $this->json(array_map(fn(Loan $loan) => $this->formatLoan($loan), $loans));
    }

    #[Route('', name: 'loan_list_all', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Get(
        path: '/api/loans',
        summary: 'Lister les emprunts actifs',
        parameters: [
            new OA\Parameter(name: 'is_late', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des emprunts actifs'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function listAll(Request $request): JsonResponse
    {
        $isLate = $request->query->has('is_late')
            ? filter_var($request->query->get('is_late'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $loans = $this->loanService->getAllActiveLoans($isLate);

        return $this->json(array_map(fn(Loan $loan) => $this->formatLoan($loan), $loans));
    }

    #[Route('/{id}/return', name: 'loan_return_request', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Patch(
        path: '/api/loans/{id}/return',
        summary: 'Demander le retour d un livre',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Retour demande avec succes'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Emprunt introuvable'),
            new OA\Response(response: 409, description: 'Retour deja demande ou emprunt deja retourne'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function returnLoan(int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $loan = $this->loanRepository->find($id);

        if (!$loan) {
            return $this->json(['error' => 'Emprunt introuvable.'], 404);
        }

        if (!$this->isLoanOwnedByUser($loan, $currentUser)) {
            return $this->json(['error' => 'Acces refuse.'], 403);
        }

        try {
            $loan = $this->loanService->requestReturn($loan);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatLoan($loan));
    }

    #[Route('/{id}/validate-return', name: 'loan_validate_return', methods: ['PATCH'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    #[OA\Patch(
        path: '/api/loans/{id}/validate-return',
        summary: 'Valider le retour d un livre',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Retour valide avec succes'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces refuse'),
            new OA\Response(response: 404, description: 'Emprunt introuvable'),
            new OA\Response(response: 409, description: 'Aucune demande en attente ou emprunt deja retourne'),
        ]
    )]
    #[Security(name: 'Bearer')]
    public function validateReturn(int $id): JsonResponse
    {
        $loan = $this->loanRepository->find($id);

        if (!$loan) {
            return $this->json(['error' => 'Emprunt introuvable.'], 404);
        }

        try {
            $loan = $this->loanService->validateReturn($loan);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->formatLoan($loan));
    }

    private function formatLoan(Loan $loan): array
    {
        $user = $loan->getUser();

        return [
            'id' => $loan->getId(),
            'bookId' => $loan->getBook()->getId(),
            'bookTitle' => $loan->getBook()->getTitle(),
            'userId' => $user->getId(),
            'user' => [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
            'loanDate' => $loan->getLoanDate()->format('Y-m-d'),
            'dueDate' => $loan->getDueDate()->format('Y-m-d'),
            'returnedAt' => $loan->getReturnedAt()?->format('Y-m-d'),
            'status' => $loan->getStatus(),
            'isLate' => $loan->isLate(),
        ];
    }

    private function isLoanOwnedByUser(Loan $loan, User $user): bool
    {
        $owner = $loan->getUser();

        if (!$owner) {
            return false;
        }

        if ($owner->getId() !== null && $user->getId() !== null) {
            return $owner->getId() === $user->getId();
        }

        return $owner === $user;
    }
}
