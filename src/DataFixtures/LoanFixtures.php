<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LoanFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Emprunt 1 : actif, pas en retard
        $loan1 = new Loan();
        $loan1->setUser($this->getReference(UserFixtures::USER_REFERENCE, User::class));
        $loan1->setBook($this->getReference(BookFixtures::BOOK_REFERENCE_1, Book::class));
        $loan1->setDueDate(new \DateTimeImmutable('+14 days'));
        $manager->persist($loan1);

        // Emprunt 2 : actif, en retard
        $loan2 = new Loan();
        $loan2->setUser($this->getReference(UserFixtures::USER_REFERENCE, User::class));
        $loan2->setBook($this->getReference(BookFixtures::BOOK_REFERENCE_2, Book::class));
        $loan2->setDueDate(new \DateTimeImmutable('-3 days'));
        $loan2->setIsLate(true);
        $loan2->setStatus(Loan::STATUS_OVERDUE);
        $manager->persist($loan2);

        // Emprunt 3 : déjà rendu
        $loan3 = new Loan();
        $loan3->setUser($this->getReference(UserFixtures::LIBRARIAN_REFERENCE, User::class));
        $loan3->setBook($this->getReference(BookFixtures::BOOK_REFERENCE_1, Book::class));
        $loan3->setDueDate(new \DateTimeImmutable('-10 days'));
        $loan3->setReturnedAt(new \DateTimeImmutable('-2 days'));
        $loan3->setStatus(Loan::STATUS_RETURNED);
        $manager->persist($loan3);

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
