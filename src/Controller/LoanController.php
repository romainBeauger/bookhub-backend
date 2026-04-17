<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Services\LoanService;
use App\Entity\Loan;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/loans')]
class LoanController extends AbstractController
{
    public function __construct(
        private readonly LoanService    $loanService,
        private readonly BookRepository $bookRepository,
        private readonly LoanRepository $loanRepository,
    ) {}

    #[Route('', name: 'loan_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
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
    public function myLoans(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $loans = $this->loanService->getLoansByUser($user);

        return $this->json(array_map(fn($l) => $this->formatLoan($l), $loans));
    }

    #[Route('', name: 'loan_list_all', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
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
