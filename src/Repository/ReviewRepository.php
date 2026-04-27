<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findByUserAndBook(User $user, Book $book): ?Review
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /** @return Review[] */
    public function getReviewsForBook(Book $book, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.book = :book')
            ->setParameter('book', $book)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Batch-load recensioni dell'utente per un set di libri (1 query).
     * @param int[] $bookIds
     * @return array<int, Review>  book_id => Review
     */
    public function findByUserForBooks(User $user, array $bookIds): array
    {
        if (empty($bookIds)) {
            return [];
        }

        $reviews = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.book IN (:bookIds)')
            ->setParameter('user', $user)
            ->setParameter('bookIds', $bookIds)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($reviews as $review) {
            $map[$review->getBook()->getId()] = $review;
        }
        return $map;
    }

    /** Media voto per un libro */
    public function getAverageRatingForBook(Book $book): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating')
            ->where('r.book = :book')
            ->setParameter('book', $book)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float) $result, 1) : null;
    }
}
