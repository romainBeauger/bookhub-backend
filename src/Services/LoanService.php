<?php

namespace App\Services;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class LoanService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoanRepository         $loanRepository,
    ) {}

    public function createLoan(User $user, Book $book): Loan
    {
        if ($book->getAvailableCopies() <= 0) {
            throw new \RuntimeException('Ce livre n\'est pas disponible.');
        }

        if ($this->loanRepository->findActiveLoanByUserAndBook($user, $book)) {
            throw new \RuntimeException('Vous avez déjà ce livre en cours d\'emprunt.');
        }

        $loan = new Loan();
        $loan->setUser($user);
        $loan->setBook($book);
        $loan->setDueDate(new \DateTimeImmutable('+14 days'));

        $book->setAvailableCopies($book->getAvailableCopies() - 1);

        $this->em->persist($loan);
        $this->em->flush();

        return $loan;
    }

    public function returnLoan(Loan $loan): Loan
    {
        if ($loan->getStatus() === Loan::STATUS_RETURNED) {
            throw new \RuntimeException('Cet emprunt a déjà été retourné.');
        }

        $loan->setReturnedAt(new \DateTimeImmutable());
        $loan->setStatus(Loan::STATUS_RETURNED);

        $book = $loan->getBook();
        $book->setAvailableCopies($book->getAvailableCopies() + 1);

        $this->em->flush();

        return $loan;
    }

    public function getLoansByUser(User $user): array
    {
        return $this->loanRepository->findByUser($user);
    }

    public function getAllActiveLoans(?bool $isLate = null): array
    {
        return $this->loanRepository->findAllActive($isLate);
    }

}
