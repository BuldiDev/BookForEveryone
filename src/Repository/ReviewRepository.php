<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità Review.
 *
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Trova la recensione di un utente per un libro specifico.
     * Usato per aggiornare la recensione esistente invece di crearne una duplicata.
     */
    public function findByUserAndBook(User $user, Book $book): ?Review
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /**
     * Restituisce le recensioni di un libro ordinate per data decrescente.
     * Usato nella scheda dettaglio del libro e nel pannello espandibile del feed.
     *
     * @return Review[]
     */
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
     * Carica in batch le recensioni dell'utente per un set di libri con una singola query.
     * Evita N query nel loop di rendering delle card del feed per mostrare
     * il voto già assegnato dall'utente su ogni libro.
     *
     * @param int[] $bookIds
     * @return array<int, Review> Indicizzato per book_id
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

        // Indicizza per book_id per un accesso O(1) nel template
        $map = [];
        foreach ($reviews as $review) {
            $map[$review->getBook()->getId()] = $review;
        }
        return $map;
    }

    /**
     * Calcola la valutazione media di un libro tramite query aggregata.
     * Alternativa leggera a Book::getAverageRating() quando non si ha l'entità caricata.
     */
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
