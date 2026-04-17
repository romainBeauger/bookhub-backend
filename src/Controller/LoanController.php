<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Services\LoanService;
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
}
