<?php

namespace App\Services;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class LoanService
{
    private const MAX_ACTIVE_LOANS_PER_USER = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private LoanRepository $loanRepository,
    ) {}

    public function createLoan(User $user, Book $book): Loan
    {
        if ($book->getAvailableCopies() <= 0) {
            throw new \RuntimeException('Ce livre n\'est pas disponible.');
        }

        if ($this->loanRepository->findActiveLoanByUserAndBook($user, $book)) {
            throw new \RuntimeException('Vous avez deja ce livre en cours d\'emprunt.');
        }

        if ($this->loanRepository->countActiveByUser($user) >= self::MAX_ACTIVE_LOANS_PER_USER) {
            throw new \RuntimeException('Vous avez atteint la limite de 3 livres empruntes.');
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

    public function requestReturn(Loan $loan): Loan
    {
        if ($loan->getStatus() === Loan::STATUS_RETURNED) {
            throw new \RuntimeException('Cet emprunt a deja ete retourne.');
        }

        if ($loan->getStatus() === Loan::STATUS_RETURN_REQUESTED) {
            throw new \RuntimeException('Le retour de cet emprunt est deja en attente de validation.');
        }

        $loan->setStatus(Loan::STATUS_RETURN_REQUESTED);
        $this->em->flush();

        return $loan;
    }

    public function validateReturn(Loan $loan): Loan
    {
        if ($loan->getStatus() === Loan::STATUS_RETURNED) {
            throw new \RuntimeException('Cet emprunt a deja ete retourne.');
        }

        if ($loan->getStatus() !== Loan::STATUS_RETURN_REQUESTED) {
            throw new \RuntimeException('Aucune demande de retour en attente pour cet emprunt.');
        }

        $loan->setReturnedAt(new \DateTimeImmutable());
        $loan->setStatus(Loan::STATUS_RETURNED);
        $loan->setIsLate(false);

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
