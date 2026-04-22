<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * @return Reservation[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.reservationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findAllWithFilters(?string $status = null, ?int $bookId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.book', 'b')
            ->leftJoin('r.user', 'u')
            ->addSelect('b', 'u')
            ->orderBy('r.queuePosition', 'ASC')
            ->addOrderBy('r.reservationDate', 'ASC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($bookId !== null) {
            $qb->andWhere('b.id = :bookId')
                ->setParameter('bookId', $bookId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countActiveForBook(int $bookId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.book', 'b')
            ->andWhere('b.id = :bookId')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('bookId', $bookId)
            ->setParameter('statuses', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_READY,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveReservationByUserAndBook(User $user, int $bookId): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->join('r.book', 'b')
            ->andWhere('r.user = :user')
            ->andWhere('b.id = :bookId')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('bookId', $bookId)
            ->setParameter('statuses', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_READY,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Reservation[]
     */
    public function findActiveQueueForBook(int $bookId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.book', 'b')
            ->addSelect('b')
            ->andWhere('b.id = :bookId')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('bookId', $bookId)
            ->setParameter('statuses', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_READY,
            ])
            ->orderBy('r.queuePosition', 'ASC')
            ->addOrderBy('r.reservationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
