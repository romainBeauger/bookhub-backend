<?php

namespace App\Tests\Service;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Services\LoanService;

class LoanServiceTest extends TestCase
{
    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepository;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    private LoanService $loanService;

    protected function setUp(): void
    {
        $this->loanRepository = $this->createMock(LoanRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->loanService = new LoanService($this->em, $this->loanRepository);
    }

    // Vérifie que createLoan() lève une exception si le livre n'a plus de copies disponibles
    public function testCannotBorrowUnavailableBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(0);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ce livre n\'est pas disponible.');
        $this->loanService->createLoan($user, $book);
    }

    // Vérifie que createLoan() lève une exception si l'utilisateur a déjà ce livre en cours d'emprunt
    public function testCannotBorrowAlreadyBorrowedBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(2);

        // On dit au mock : ce livre est déjà emprunté par cet utilisateur
        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(new Loan());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez déjà ce livre en cours d\'emprunt.');

        $this->loanService->createLoan($user, $book);
    }

    // Vérifie que la date de retour calculée est bien aujourd'hui + 14 jours
    public function testLoanCreatedWithCorrectDueDate(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(3);

        // Pas d'emprunt actif existant
        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(null);

        // persist() et flush() ne font rien (mocks)
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $loan = $this->loanService->createLoan($user, $book);

        $expectedDueDate = (new \DateTimeImmutable('+14 days'))->format('Y-m-d');
        $this->assertSame($expectedDueDate, $loan->getDueDate()->format('Y-m-d'));
    }

    // Vérifie que returnLoan() restitue bien +1 copie disponible au livre
    public function testReturnLoanSetsBookAvailable(): void
    {
        $book = (new Book())->setAvailableCopies(1);
        $loan = (new Loan())->setBook($book);

        $this->em->expects($this->once())->method('flush');

        $this->loanService->returnLoan($loan);

        // Le livre doit avoir récupéré +1 copie disponible
        $this->assertSame(2, $book->getAvailableCopies());
    }

    // Vérifie que returnLoan() passe bien le statut de l'emprunt à RETURNED
    public function testReturnLoanSetsStatusReturned(): void
    {
        $book = (new Book())->setAvailableCopies(1);
        $loan = (new Loan())->setBook($book);

        $this->em->expects($this->once())->method('flush');

        $this->loanService->returnLoan($loan);

        $this->assertSame(Loan::STATUS_RETURNED, $loan->getStatus());
    }
}
