<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * @return Loan[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.loanDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveLoanByUserAndBook(User $user, Book $book): ?Loan
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.book = :book')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.user = :user')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Loan[]
     */
    public function findOverdueLoans(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.dueDate < :now')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(?bool $isLate = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->orderBy('l.loanDate', 'DESC');

        if ($isLate !== null) {
            $qb->andWhere('l.isLate = :isLate')
                ->setParameter('isLate', $isLate);
        }

        return $qb->getQuery()->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLate(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.isLate = true')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [
                Loan::STATUS_ACTIVE,
                Loan::STATUS_OVERDUE,
                Loan::STATUS_RETURN_REQUESTED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
