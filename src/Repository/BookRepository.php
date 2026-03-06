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
     * Ordine: prima libri con generi preferiti, poi tutti gli altri.
     */
    public function getFeedForUser(User $user, int $page = 1, int $limit = 10, ?int $seed = null, array $excludeIds = []): array
    {
        $genres = $user->getPreferredGenres();

        if ($seed === null) {
            $seed = random_int(1, 999999);
        }

        $preferredBooks = [];
        $otherBooks = [];

        // Se l'utente ha generi preferiti, separiamo i libri
        if (!$genres->isEmpty()) {
            // Libri con i generi preferiti
            $qb1 = $this->createQueryBuilder('b')
                ->leftJoin('b.genres', 'g')
                ->leftJoin('b.photos', 'p')
                ->where('g IN (:genres)')
                ->setParameter('genres', $genres);
            
            if (!empty($excludeIds)) {
                $qb1->andWhere('b.id NOT IN (:excludeIds)')
                    ->setParameter('excludeIds', $excludeIds);
            }
            
            $preferredBooks = $qb1->getQuery()->getResult();

            // Ottieni gli ID dei libri preferiti per escluderli dalla seconda query
            $preferredIds = array_map(fn($b) => $b->getId(), $preferredBooks);

            // Tutti gli altri libri
            $qb2 = $this->createQueryBuilder('b')
                ->leftJoin('b.photos', 'p');
            
            $excludeAll = array_merge($preferredIds, $excludeIds);
            if (!empty($excludeAll)) {
                $qb2->where('b.id NOT IN (:excludeAll)')
                    ->setParameter('excludeAll', $excludeAll);
            }
            
            $otherBooks = $qb2->getQuery()->getResult();
        } else {
            // Se non ha generi preferiti, mostra tutti i libri
            $qb = $this->createQueryBuilder('b')
                ->leftJoin('b.photos', 'p');
            
            if (!empty($excludeIds)) {
                $qb->where('b.id NOT IN (:excludeIds)')
                    ->setParameter('excludeIds', $excludeIds);
            }
            
            $otherBooks = $qb->getQuery()->getResult();
        }

        // Randomizza ogni gruppo separatamente con lo stesso seed
        usort($preferredBooks, function($a, $b) use ($seed) {
            $hashA = crc32($a->getId() . '-preferred-' . $seed);
            $hashB = crc32($b->getId() . '-preferred-' . $seed);
            return $hashA <=> $hashB;
        });

        usort($otherBooks, function($a, $b) use ($seed) {
            $hashA = crc32($a->getId() . '-other-' . $seed);
            $hashB = crc32($b->getId() . '-other-' . $seed);
            return $hashA <=> $hashB;
        });

        // Unisci: prima i preferiti, poi gli altri
        $allBooks = array_merge($preferredBooks, $otherBooks);

        // Restituiamo la pagina richiesta
        $offset = ($page - 1) * $limit;
        return array_slice($allBooks, $offset, $limit);
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
