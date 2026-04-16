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
     * Ordine: prima libri con generi preferiti, poi tutti gli altri, random con seed.
     * Ottimizzato: 1 query nativa per gli ID con LIMIT, poi batch-hydrate.
     */
    public function getFeedForUser(User $user, int $page = 1, int $limit = 10, ?int $seed = null, array $excludeIds = []): array
    {
        $genres = $user->getPreferredGenres();

        if ($seed === null) {
            $seed = random_int(1, 999999);
        }

        $conn = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;

        // === Fase 1: Query nativa per gli ID con ORDER BY + LIMIT ===
        if (!$genres->isEmpty()) {
            $genreIds = array_map(fn($g) => $g->getId(), $genres->toArray());

            $sql = 'SELECT b.id, '
                . 'MAX(CASE WHEN bg2.genre_id IS NOT NULL THEN 0 ELSE 1 END) AS pref, '
                . 'CRC32(CONCAT(b.id, :seed)) AS rnd '
                . 'FROM book b '
                . 'LEFT JOIN book_genre bg2 ON bg2.book_id = b.id AND bg2.genre_id IN (:genreIds) ';

            $params = ['seed' => (string) $seed, 'genreIds' => $genreIds];
            $types = ['genreIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER];

            if (!empty($excludeIds)) {
                $sql .= 'WHERE b.id NOT IN (:excludeIds) ';
                $params['excludeIds'] = $excludeIds;
                $types['excludeIds'] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
            }

            $sql .= 'GROUP BY b.id ORDER BY pref ASC, rnd ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        } else {
            $sql = 'SELECT b.id, CRC32(CONCAT(b.id, :seed)) AS rnd FROM book b ';

            $params = ['seed' => (string) $seed];
            $types = [];

            if (!empty($excludeIds)) {
                $sql .= 'WHERE b.id NOT IN (:excludeIds) ';
                $params['excludeIds'] = $excludeIds;
                $types['excludeIds'] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
            }

            $sql .= 'ORDER BY rnd ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $pageIds = array_map('intval', array_column($conn->executeQuery($sql, $params, $types)->fetchAllAssociative(), 'id'));

        if (empty($pageIds)) {
            return [];
        }

        // === Fase 2: Carica entità con addedBy e generi (1 sola DQL) ===
        $books = $this->createQueryBuilder('b')
            ->leftJoin('b.addedBy', 'a')->addSelect('a')
            ->leftJoin('b.genres', 'g')->addSelect('g')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getResult();

        // Mantieni l'ordine dalla query SQL
        $idOrder = array_flip($pageIds);
        usort($books, fn($a, $b) => $idOrder[$a->getId()] <=> $idOrder[$b->getId()]);

        return $books;
    }

    /**
     * Calcola averageRating e likesCount per un set di libri in 2 query native.
     * @param int[] $bookIds
     * @return array<int, array{avg_rating: ?float, likes_count: int}>
     */
    public function getBookStats(array $bookIds): array
    {
        if (empty($bookIds)) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        // Rating medi
        $rows = $conn->executeQuery(
            'SELECT book_id, ROUND(AVG(rating), 1) AS avg_rating FROM review WHERE book_id IN (:ids) GROUP BY book_id',
            ['ids' => $bookIds],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $stats = [];
        foreach ($bookIds as $id) {
            $stats[$id] = ['avg_rating' => null, 'likes_count' => 0];
        }
        foreach ($rows as $row) {
            $stats[(int) $row['book_id']]['avg_rating'] = (float) $row['avg_rating'];
        }

        // Likes count
        $rows = $conn->executeQuery(
            'SELECT book_id, COUNT(*) AS likes_count FROM user_book WHERE book_id IN (:ids) AND liked = 1 GROUP BY book_id',
            ['ids' => $bookIds],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $stats[(int) $row['book_id']]['likes_count'] = (int) $row['likes_count'];
        }

        return $stats;
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

    /**
     * Libri suggeriti da persone che l'utente segue (interazioni o aggiunti dai seguiti).
     * @param int[] $excludeIds
     * @return Book[]
     */
    public function getBooksFromFollowedUsers(User $user, int $page = 1, int $limit = 30, array $excludeIds = []): array
    {
        $following = $user->getFollowing();
        if ($following->isEmpty()) {
            return [];
        }

        $followingIds = array_map(fn($u) => $u->getId(), $following->toArray());

        $conn = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;

        // Libri con qualsiasi interazione dai seguiti UNION libri aggiunti dai seguiti
        $sql = 'SELECT book_id AS id, MAX(ts) AS last_ts FROM ('
            . '  SELECT ub.book_id, COALESCE(ub.updated_at, ub.created_at) AS ts'
            . '  FROM user_book ub'
            . '  WHERE ub.user_id IN (:followingIds)'
            . '    AND (ub.liked = 1 OR ub.has_read = 1 OR ub.owns_physically = 1)'
            . '  UNION ALL'
            . '  SELECT b.id AS book_id, b.created_at AS ts'
            . '  FROM book b'
            . '  WHERE b.added_by_id IN (:followingIds)'
            . ') AS sub ';

        $params = ['followingIds' => $followingIds];
        $types = ['followingIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER];

        if (!empty($excludeIds)) {
            $sql .= 'WHERE book_id NOT IN (:excludeIds) ';
            $params['excludeIds'] = $excludeIds;
            $types['excludeIds'] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
        }

        $sql .= 'GROUP BY book_id ORDER BY last_ts DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $pageIds = array_map('intval', array_column(
            $conn->executeQuery($sql, $params, $types)->fetchAllAssociative(),
            'id'
        ));

        if (empty($pageIds)) {
            return [];
        }

        $books = $this->createQueryBuilder('b')
            ->leftJoin('b.addedBy', 'a')->addSelect('a')
            ->leftJoin('b.genres', 'g')->addSelect('g')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getResult();

        $idOrder = array_flip($pageIds);
        usort($books, fn($a, $b) => $idOrder[$a->getId()] <=> $idOrder[$b->getId()]);

        return $books;
    }
}
