<?php

namespace App\Services;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class ReservationService
{
    private const MAX_ACTIVE_RESERVATIONS_PER_USER = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepository,
        private LoanService $loanService,
    ) {}

    public function createReservation(User $user, Book $book): Reservation
    {
        if ($this->reservationRepository->findActiveReservationByUserAndBook($user, (int) $book->getId())) {
            throw new \RuntimeException('Vous avez deja une reservation active pour ce livre.');
        }

        if ($this->reservationRepository->countActiveByUser($user) >= self::MAX_ACTIVE_RESERVATIONS_PER_USER) {
            throw new \RuntimeException('Vous avez atteint la limite de 5 reservations actives.');
        }

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setBook($book);
        $reservation->setQueuePosition($this->reservationRepository->countActiveForBook((int) $book->getId()) + 1);

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    /**
     * @return Reservation[]
     */
    public function getReservationsByUser(User $user): array
    {
        return $this->reservationRepository->findByUser($user);
    }

    /**
     * @return Reservation[]
     */
    public function getAllReservations(?string $status = null, ?int $bookId = null, ?string $userName = null): array
    {
        return $this->reservationRepository->findAllWithFilters($status, $bookId, $userName);
    }

    public function markReady(Reservation $reservation): Reservation
    {
        if ($reservation->getStatus() === Reservation::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette reservation est annulee.');
        }

        if ($reservation->getStatus() === Reservation::STATUS_VALIDATED) {
            throw new \RuntimeException('Cette reservation a deja ete validee.');
        }

        if ($reservation->getStatus() === Reservation::STATUS_READY) {
            throw new \RuntimeException('Cette reservation est deja prete.');
        }

        $firstReservation = $this->getFirstActiveReservationForBook($reservation);

        if ($firstReservation === null || $firstReservation->getId() !== $reservation->getId()) {
            throw new \RuntimeException('Seule la premiere reservation de la file peut etre marquee comme prete.');
        }

        if ($reservation->getBook()->getAvailableCopies() <= 0) {
            throw new \RuntimeException('Aucun exemplaire n est actuellement disponible pour ce livre.');
        }

        $reservation->setStatus(Reservation::STATUS_READY);
        $this->em->flush();

        return $reservation;
    }

    /**
     * @return array{reservation: Reservation, loan: Loan}
     */
    public function validateReservation(Reservation $reservation): array
    {
        if ($reservation->getStatus() === Reservation::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette reservation est annulee.');
        }

        if ($reservation->getStatus() === Reservation::STATUS_VALIDATED) {
            throw new \RuntimeException('Cette reservation a deja ete validee.');
        }

        if ($reservation->getStatus() !== Reservation::STATUS_READY) {
            throw new \RuntimeException('La reservation doit etre marquee comme prete avant validation.');
        }

        $loan = $this->loanService->createLoan($reservation->getUser(), $reservation->getBook());

        $reservation->setStatus(Reservation::STATUS_VALIDATED);
        $this->reorderQueue($reservation->getBook());
        $this->em->flush();

        return [
            'reservation' => $reservation,
            'loan' => $loan,
        ];
    }

    public function cancelReservation(Reservation $reservation): Reservation
    {
        if ($reservation->getStatus() === Reservation::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette reservation est deja annulee.');
        }

        if ($reservation->getStatus() === Reservation::STATUS_VALIDATED) {
            throw new \RuntimeException('Une reservation deja validee ne peut pas etre annulee.');
        }

        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $this->reorderQueue($reservation->getBook());
        $this->em->flush();

        return $reservation;
    }

    private function getFirstActiveReservationForBook(Reservation $reservation): ?Reservation
    {
        $queue = $this->reservationRepository->findActiveQueueForBook((int) $reservation->getBook()->getId());

        return $queue[0] ?? null;
    }

    private function reorderQueue(Book $book): void
    {
        $queue = $this->reservationRepository->findActiveQueueForBook((int) $book->getId());

        foreach ($queue as $index => $queuedReservation) {
            $queuedReservation->setQueuePosition($index + 1);
        }
    }
}
