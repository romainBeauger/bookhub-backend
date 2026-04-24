<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $reservations = [
            [
                'user' => UserFixtures::USER_REFERENCE,
                'book' => BookFixtures::BOOK_REFERENCE_1,
                'date' => '-4 days',
                'status' => Reservation::STATUS_READY,
                'queuePosition' => 1,
            ],
            [
                'user' => UserFixtures::LIBRARIAN_REFERENCE,
                'book' => BookFixtures::BOOK_REFERENCE_1,
                'date' => '-2 days',
                'status' => Reservation::STATUS_PENDING,
                'queuePosition' => 2,
            ],
            [
                'user' => UserFixtures::USER_REFERENCE,
                'book' => BookFixtures::BOOK_REFERENCE_2,
                'date' => '-6 days',
                'status' => Reservation::STATUS_VALIDATED,
                'queuePosition' => 1,
            ],
            [
                'user' => UserFixtures::LIBRARIAN_REFERENCE,
                'book' => BookFixtures::BOOK_REFERENCE_2,
                'date' => '-1 day',
                'status' => Reservation::STATUS_CANCELLED,
                'queuePosition' => 2,
            ],
        ];

        foreach ($reservations as $reservationData) {
            $reservation = new Reservation();
            $reservation->setUser($this->getReference($reservationData['user'], User::class));
            $reservation->setBook($this->getReference($reservationData['book'], Book::class));
            $reservation->setReservationDate(new \DateTimeImmutable($reservationData['date']));
            $reservation->setStatus($reservationData['status']);
            $reservation->setQueuePosition($reservationData['queuePosition']);

            $manager->persist($reservation);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            BookFixtures::class,
        ];
    }
}
