<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\ArrayParameterType;

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
     * Retourne tous les emprunts d'un utilisateur, du plus récent au plus ancien.
     *
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

    /**
     * Retourne l'emprunt actif d'un user pour un livre donné, ou null s'il n'existe pas.
     * Utilisé pour vérifier qu'un user n'emprunte pas deux fois le même livre.
     */
    public function findActiveLoanByUserAndBook(User $user, Book $book): ?Loan
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.book = :book')
            ->andWhere('l.status = :status')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->setParameter('status', Loan::STATUS_ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les emprunts en retard (dueDate dépassée et pas encore rendus).
     *
     * @return Loan[]
     */
    public function findOverdueLoans(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.dueDate < :now')
            ->andWhere('l.status = :status')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', Loan::STATUS_ACTIVE)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(?bool $isLate = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status = :statusActive OR l.status = :statusOverdue')
            ->setParameter('statusActive', Loan::STATUS_ACTIVE)
            ->setParameter('statusOverdue', Loan::STATUS_OVERDUE)
            ->orderBy('l.loanDate', 'DESC');

        if ($isLate !== null) {
            $qb->andWhere('l.isLate = :isLate')
                ->setParameter('isLate', $isLate);
        }

        return $qb->getQuery()->getResult();
    }

}
