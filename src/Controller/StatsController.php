<?php

namespace App\Controller;

use App\Repository\LoanRepository;
use App\Entity\Loan;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly LoanRepository $loanRepository,
    ) {}

    #[Route('/loans', name: 'stats_loans', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
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
