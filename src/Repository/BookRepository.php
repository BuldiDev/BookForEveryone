<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Genre;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * Feed TikTok: libri filtrati per generi preferiti dell'utente.
     */
    public function getFeedForUser(User $user, int $page = 1, int $limit = 10): Paginator
    {
        $genres = $user->getPreferredGenres();

        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.genres', 'g')
            ->leftJoin('b.photos', 'p');

        if (!$genres->isEmpty()) {
            $qb->where('g IN (:genres)')
               ->setParameter('genres', $genres);
        }

        $qb->orderBy('b.createdAt', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }

    /**
     * Esplora: tutti i libri recenti (per utenti non loggati o discover).
     */
    public function getRecentBooks(int $page = 1, int $limit = 10): Paginator
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }

    /**
     * Libri per genere specifico.
     */
    public function getBooksByGenre(Genre $genre, int $page = 1, int $limit = 10): Paginator
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.genres', 'g')
            ->where('g = :genre')
            ->setParameter('genre', $genre)
            ->orderBy('b.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }

    /**
     * Cerca libri per titolo o autore.
     * @return Book[]
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.title LIKE :q OR b.bookAuthor LIKE :q OR b.isbn LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trova un libro per ISBN (per evitare duplicati).
     */
    public function findByIsbn(string $isbn): ?Book
    {
        return $this->findOneBy(['isbn' => $isbn]);
    }

    /**
     * Cerca libro esistente per titolo e autore (match approssimativo).
     * @return Book[]
     */
    public function findSimilar(string $title, string $author, int $limit = 5): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.title LIKE :title AND b.bookAuthor LIKE :author')
            ->setParameter('title', '%' . $title . '%')
            ->setParameter('author', '%' . $author . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
