<?php

namespace App\Tests\Service;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\LoanRepository;
use App\Repository\ReservationRepository;
use App\Services\LoanService;
use App\Services\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReservationServiceTest extends TestCase
{
    /** @var ReservationRepository&MockObject */
    private ReservationRepository $reservationRepository;

    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepository;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var LoanService&MockObject */
    private LoanService $loanService;

    private ReservationService $reservationService;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->loanRepository = $this->createMock(LoanRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->loanService = $this->createMock(LoanService::class);
        $this->reservationService = new ReservationService(
            $this->em,
            $this->reservationRepository,
            $this->loanRepository,
            $this->loanService,
        );
    }

    public function testCannotReserveAvailableBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(1)->setTotalCopies(4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La reservation est possible uniquement quand le livre est indisponible.');

        $this->reservationService->createReservation($user, $book);
    }

    public function testCannotReserveAlreadyBorrowedBook(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(0)->setTotalCopies(4);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->with($user, $book)
            ->willReturn(new Loan());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vous avez deja ce livre en cours d\'emprunt.');

        $this->reservationService->createReservation($user, $book);
    }

    public function testReservationCanBeCreatedWhenBookIsUnavailable(): void
    {
        $user = new User();
        $book = (new Book())->setAvailableCopies(0)->setTotalCopies(4);
        $this->setEntityId($book, 12);

        $this->loanRepository
            ->method('findActiveLoanByUserAndBook')
            ->with($user, $book)
            ->willReturn(null);

        $this->reservationRepository
            ->method('findActiveReservationByUserAndBook')
            ->with($user, 12)
            ->willReturn(null);

        $this->reservationRepository
            ->method('countActiveByUser')
            ->with($user)
            ->willReturn(0);

        $this->reservationRepository
            ->method('countActiveForBook')
            ->with(12)
            ->willReturn(0);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $reservation = $this->reservationService->createReservation($user, $book);

        $this->assertSame($user, $reservation->getUser());
        $this->assertSame($book, $reservation->getBook());
        $this->assertSame(1, $reservation->getQueuePosition());
        $this->assertSame(Reservation::STATUS_PENDING, $reservation->getStatus());
    }

    public function testCancelReservationMarksItCancelled(): void
    {
        $book = new Book();
        $this->setEntityId($book, 4);
        $reservation = (new Reservation())
            ->setBook($book)
            ->setStatus(Reservation::STATUS_PENDING)
            ->setQueuePosition(1);

        $this->reservationRepository
            ->method('findActiveQueueForBook')
            ->with(4)
            ->willReturn([]);

        $this->em->expects($this->once())->method('flush');

        $this->reservationService->cancelReservation($reservation);

        $this->assertSame(Reservation::STATUS_CANCELLED, $reservation->getStatus());
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setValue($entity, $id);
    }
}
