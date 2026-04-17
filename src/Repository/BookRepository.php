<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * @return Book[]
     */
    public function findPaginated(int $page, int $limit): array
    {
        return $this->findPaginatedWithFilters($page, $limit, []);
    }

    /**
     * @param array{
     *     q?: string,
     *     author?: string,
     *     categoryId?: int,
     *     available?: bool,
     *     publishedFrom?: \DateTimeImmutable,
     *     publishedTo?: \DateTimeImmutable
     * } $filters
     *
     * @return Book[]
     */
    public function findPaginatedWithFilters(int $page, int $limit, array $filters): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createFilteredQueryBuilder($filters)
            ->orderBy('b.title', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countAll(): int
    {
        return $this->countFiltered([]);
    }

    /**
     * @param array{
     *     q?: string,
     *     author?: string,
     *     categoryId?: int,
     *     available?: bool,
     *     publishedFrom?: \DateTimeImmutable,
     *     publishedTo?: \DateTimeImmutable
     * } $filters
     */
    public function countFiltered(array $filters): int
    {
        return (int) $this->createFilteredQueryBuilder($filters)
            ->select('COUNT(DISTINCT b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{
     *     q?: string,
     *     author?: string,
     *     categoryId?: int,
     *     available?: bool,
     *     publishedFrom?: \DateTimeImmutable,
     *     publishedTo?: \DateTimeImmutable
     * } $filters
     */
    private function createFilteredQueryBuilder(array $filters): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->leftJoin('b.category', 'c')
            ->addSelect('c');

        if (!empty($filters['q'])) {
            $search = '%' . mb_strtolower($filters['q']) . '%';
            $queryBuilder
                ->andWhere('LOWER(b.title) LIKE :search OR LOWER(b.author) LIKE :search OR LOWER(COALESCE(b.description, \'\')) LIKE :search OR LOWER(b.isbn) LIKE :search OR LOWER(c.name) LIKE :search')
                ->setParameter('search', $search);
        }

        if (!empty($filters['author'])) {
            $queryBuilder
                ->andWhere('LOWER(b.author) LIKE :author')
                ->setParameter('author', '%' . mb_strtolower($filters['author']) . '%');
        }

        if (isset($filters['categoryId'])) {
            $queryBuilder
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $filters['categoryId']);
        }

        if (isset($filters['available'])) {
            $operator = $filters['available'] ? '>' : '=';
            $queryBuilder->andWhere(sprintf('b.availableCopies %s 0', $operator));
        }

        if (isset($filters['publishedFrom'])) {
            $queryBuilder
                ->andWhere('b.publishedAt >= :publishedFrom')
                ->setParameter('publishedFrom', $filters['publishedFrom'], Types::DATE_IMMUTABLE);
        }

        if (isset($filters['publishedTo'])) {
            $queryBuilder
                ->andWhere('b.publishedAt <= :publishedTo')
                ->setParameter('publishedTo', $filters['publishedTo'], Types::DATE_IMMUTABLE);
        }

        return $queryBuilder;
    }
}
