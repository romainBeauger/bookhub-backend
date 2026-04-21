<?php

namespace App\Tests\Service;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Repository\LoanRepository;
use App\Services\LoanService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

    public function testCannotBorrowUnavailableBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ce livre n\'est pas disponible.');

        $this->loanService->createLoan($user, $book);
    }

    public function testCannotBorrowAlreadyBorrowedBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(2);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(new Loan());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez deja ce livre en cours d\'emprunt.');

        $this->loanService->createLoan($user, $book);
    }

    public function testCannotBorrowMoreThanThreeActiveBooks(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(2);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(null);

        $this->loanRepository
            ->method('countActiveByUser')
            ->with($user)
            ->willReturn(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez atteint la limite de 3 livres empruntes.');

        $this->loanService->createLoan($user, $book);
    }

    public function testLoanCreatedWithCorrectDueDate(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(3);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(null);

        $this->loanRepository
            ->method('countActiveByUser')
            ->with($user)
            ->willReturn(2);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $loan = $this->loanService->createLoan($user, $book);

        $expectedDueDate = (new \DateTimeImmutable('+14 days'))->format('Y-m-d');
        $this->assertSame($expectedDueDate, $loan->getDueDate()->format('Y-m-d'));
    }

    public function testCreateLoanDecrementsAvailableCopies(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(3);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->willReturn(null);

        $this->loanRepository
            ->method('countActiveByUser')
            ->with($user)
            ->willReturn(0);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->loanService->createLoan($user, $book);

        $this->assertSame(2, $book->getAvailableCopies());
    }

    public function testRequestReturnSetsPendingStatus(): void
    {
        $loan = new Loan();

        $this->em->expects($this->once())->method('flush');

        $this->loanService->requestReturn($loan);

        $this->assertSame(Loan::STATUS_RETURN_REQUESTED, $loan->getStatus());
        $this->assertNull($loan->getReturnedAt());
    }

    public function testCannotRequestReturnTwice(): void
    {
        $loan = (new Loan())->setStatus(Loan::STATUS_RETURN_REQUESTED);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le retour de cet emprunt est deja en attente de validation.');

        $this->loanService->requestReturn($loan);
    }

    public function testValidateReturnSetsBookAvailable(): void
    {
        $book = (new Book())->setAvailableCopies(1);
        $loan = (new Loan())
            ->setBook($book)
            ->setStatus(Loan::STATUS_RETURN_REQUESTED);

        $this->em->expects($this->once())->method('flush');

        $this->loanService->validateReturn($loan);

        $this->assertSame(2, $book->getAvailableCopies());
    }

    public function testValidateReturnSetsStatusReturned(): void
    {
        $book = (new Book())->setAvailableCopies(1);
        $loan = (new Loan())
            ->setBook($book)
            ->setStatus(Loan::STATUS_RETURN_REQUESTED);

        $this->em->expects($this->once())->method('flush');

        $this->loanService->validateReturn($loan);

        $this->assertSame(Loan::STATUS_RETURNED, $loan->getStatus());
        $this->assertNotNull($loan->getReturnedAt());
    }

    public function testCannotValidateReturnWithoutPendingRequest(): void
    {
        $book = (new Book())->setAvailableCopies(1);
        $loan = (new Loan())
            ->setBook($book)
            ->setStatus(Loan::STATUS_ACTIVE);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucune demande de retour en attente pour cet emprunt.');

        $this->loanService->validateReturn($loan);
    }
}
