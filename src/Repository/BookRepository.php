<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Genre;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository per l'entità Book.
 *
 * Contiene query ottimizzate per il feed personalizzato, la ricerca
 * geolocalizzata e le statistiche della dashboard amministrativa.
 *
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private CacheInterface $cache,
    ) {
        parent::__construct($registry, Book::class);
    }

    /**
     * Genera il feed personalizzato per l'utente in due fasi:
     *
     * Fase 1 — Query SQL nativa per recuperare gli ID con ORDER + LIMIT:
     *   - Priorità ai libri con almeno un genere tra i preferiti dell'utente
     *   - Ordine casuale deterministico tramite CRC32(id + seed) per batch coerenti
     *   - Esclude i libri già visti (seenBookIds gestiti da FeedHistoryService)
     *
     * Fase 2 — Hydration DQL: carica le entità complete solo per gli ID ottenuti,
     *   mantenendo l'ordine della fase 1.
     *
     * @param int[] $excludeIds ID dei libri già mostrati all'utente
     * @return Book[]
     */
    public function getFeedForUser(User $user, int $page = 1, int $limit = 10, ?int $seed = null, array $excludeIds = []): array
    {
        $genres = $user->getPreferredGenres();

        if ($seed === null) {
            $seed = random_int(1, 999999);
        }

        $conn   = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;

        // === Fase 1: query nativa per gli ID con ORDER BY + LIMIT ===
        if (!$genres->isEmpty()) {
            $genreIds = array_map(fn($g) => $g->getId(), $genres->toArray());

            // pref = 0 se il libro appartiene a un genere preferito, 1 altrimenti → i preferiti vengono prima
            $sql = 'SELECT b.id, '
                . 'MAX(CASE WHEN bg2.genre_id IS NOT NULL THEN 0 ELSE 1 END) AS pref, '
                . 'CRC32(CONCAT(b.id, :seed)) AS rnd '
                . 'FROM book b '
                . 'LEFT JOIN book_genre bg2 ON bg2.book_id = b.id AND bg2.genre_id IN (:genreIds) ';

            $params = ['seed' => (string) $seed, 'genreIds' => $genreIds];
            $types  = ['genreIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER];

            if (!empty($excludeIds)) {
                $sql .= 'WHERE b.id NOT IN (:excludeIds) ';
                $params['excludeIds'] = $excludeIds;
                $types['excludeIds']  = \Doctrine\DBAL\ArrayParameterType::INTEGER;
            }

            $sql .= 'GROUP BY b.id ORDER BY pref ASC, rnd ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        } else {
            // Nessun genere preferito: ordine puramente casuale con seed
            $sql    = 'SELECT b.id, CRC32(CONCAT(b.id, :seed)) AS rnd FROM book b ';
            $params = ['seed' => (string) $seed];
            $types  = [];

            if (!empty($excludeIds)) {
                $sql .= 'WHERE b.id NOT IN (:excludeIds) ';
                $params['excludeIds'] = $excludeIds;
                $types['excludeIds']  = \Doctrine\DBAL\ArrayParameterType::INTEGER;
            }

            $sql .= 'ORDER BY rnd ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $pageIds = array_map('intval', array_column($conn->executeQuery($sql, $params, $types)->fetchAllAssociative(), 'id'));

        if (empty($pageIds)) {
            return [];
        }

        // === Fase 2: carica entità complete con JOIN (1 sola query DQL) ===
        $books = $this->createQueryBuilder('b')
            ->leftJoin('b.addedBy', 'a')->addSelect('a')
            ->leftJoin('b.genres', 'g')->addSelect('g')
            ->leftJoin('b.photos', 'p')->addSelect('p')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getResult();

        // Ripristina l'ordine stabilito dalla query SQL (DQL non garantisce l'ordine di IN)
        $idOrder = array_flip($pageIds);
        usort($books, fn($a, $b) => $idOrder[$a->getId()] <=> $idOrder[$b->getId()]);

        return $books;
    }

    /**
     * Calcola valutazione media e conteggio like per un insieme di libri
     * usando 2 query SQL native aggregate invece di N query per libro.
     * Il risultato è cachato per 60 secondi.
     *
     * @param int[] $bookIds
     * @return array<int, array{avg_rating: ?float, likes_count: int}>
     */
    public function getBookStats(array $bookIds): array
    {
        if (empty($bookIds)) {
            return [];
        }

        $cacheKey = 'book_stats_' . md5(implode(',', $bookIds));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($bookIds) {
            $item->expiresAfter(60);

            $conn = $this->getEntityManager()->getConnection();

            // Query 1: valutazione media per ogni libro
            $rows = $conn->executeQuery(
                'SELECT book_id, ROUND(AVG(rating), 1) AS avg_rating FROM review WHERE book_id IN (:ids) GROUP BY book_id',
                ['ids' => $bookIds],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            )->fetchAllAssociative();

            // Inizializza tutti i valori a default
            $stats = [];
            foreach ($bookIds as $id) {
                $stats[$id] = ['avg_rating' => null, 'likes_count' => 0];
            }
            foreach ($rows as $row) {
                $stats[(int) $row['book_id']]['avg_rating'] = (float) $row['avg_rating'];
            }

            // Query 2: conteggio like per ogni libro
            $rows = $conn->executeQuery(
                'SELECT book_id, COUNT(*) AS likes_count FROM user_book WHERE book_id IN (:ids) AND liked = 1 GROUP BY book_id',
                ['ids' => $bookIds],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            )->fetchAllAssociative();

            foreach ($rows as $row) {
                $stats[(int) $row['book_id']]['likes_count'] = (int) $row['likes_count'];
            }

            return $stats;
        });
    }

    /**
     * Restituisce tutti i libri ordinati per data di inserimento (dal più recente).
     * Usato per la scoperta di libri indipendente dai generi.
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
     * Restituisce i libri appartenenti a un genere specifico, paginati.
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
     * Ricerca libri per titolo, autore o ISBN (ricerca parziale LIKE).
     *
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
     * Trova un libro per ISBN esatto.
     * Usato per impedire l'inserimento di libri duplicati.
     */
    public function findByIsbn(string $isbn): ?Book
    {
        return $this->findOneBy(['isbn' => $isbn]);
    }

    /**
     * Statistiche aggregate per tutti i libri, usate nella dashboard admin.
     * Usa subquery pre-aggregate per evitare il prodotto cartesiano tra review e user_book.
     *
     * @return array<int, array{id:int, title:string, book_author:string, cover_url:string|null, avg_rating:float|null, reviews_count:int, likes_count:int, readers_count:int}>
     */
    public function getAdminBookStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                b.id,
                b.title,
                b.book_author,
                b.cover_url,
                ROUND(r_agg.avg_rating, 1)          AS avg_rating,
                COALESCE(r_agg.reviews_count, 0)    AS reviews_count,
                COALESCE(ub_agg.likes_count, 0)     AS likes_count,
                COALESCE(ub_agg.readers_count, 0)   AS readers_count
            FROM book b
            -- Subquery per valutazioni: evita il prodotto cartesiano con user_book
            LEFT JOIN (
                SELECT book_id,
                       AVG(rating)  AS avg_rating,
                       COUNT(*)     AS reviews_count
                FROM review
                GROUP BY book_id
            ) r_agg ON r_agg.book_id = b.id
            -- Subquery per interazioni: evita il prodotto cartesiano con review
            LEFT JOIN (
                SELECT book_id,
                       SUM(liked)    AS likes_count,
                       SUM(has_read) AS readers_count
                FROM user_book
                GROUP BY book_id
            ) ub_agg ON ub_agg.book_id = b.id
            ORDER BY reviews_count DESC, likes_count DESC, b.title ASC
        ';

        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(static function (array $row): array {
            return [
                'id'            => (int) $row['id'],
                'title'         => $row['title'],
                'book_author'   => $row['book_author'],
                'cover_url'     => $row['cover_url'],
                'avg_rating'    => $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
                'reviews_count' => (int) $row['reviews_count'],
                'likes_count'   => (int) $row['likes_count'],
                'readers_count' => (int) $row['readers_count'],
            ];
        }, $rows);
    }

    /**
     * Contatori globali per la dashboard admin: libri, utenti, recensioni, prestiti.
     */
    public function getGlobalStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        return [
            'total_books'   => (int) $conn->fetchOne('SELECT COUNT(*) FROM book'),
            'total_users'   => (int) $conn->fetchOne('SELECT COUNT(*) FROM `user`'),
            'total_reviews' => (int) $conn->fetchOne('SELECT COUNT(*) FROM review'),
            'total_loans'   => (int) $conn->fetchOne('SELECT COUNT(*) FROM loan_request'),
        ];
    }

    /**
     * Ricerca approssimativa per titolo e autore: usata per rilevare possibili duplicati
     * prima di inserire un nuovo libro tramite le fixture o i comandi di popolamento.
     *
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
     * Recupera i libri consigliati dagli utenti seguiti.
     *
     * Logica: un libro è "dei seguiti" se almeno uno degli utenti seguiti
     * lo ha messo like, lo ha letto, lo possiede, oppure lo ha aggiunto al catalogo.
     * Esclude i libri già visti (deduplicazione tramite FeedHistoryService).
     *
     * @param int[] $excludeIds
     * @return Book[]
     */
    public function getBooksFromFollowedUsers(User $user, int $page = 1, int $limit = 30, array $excludeIds = []): array
    {
        $conn   = $this->getEntityManager()->getConnection();
        $offset = ($page - 1) * $limit;
        $userId = $user->getId();

        // UNION: interazioni dei seguiti + libri aggiunti dai seguiti
        $sql = 'SELECT book_id AS id, MAX(ts) AS last_ts FROM ('
            . '  SELECT ub.book_id, COALESCE(ub.updated_at, ub.created_at) AS ts'
            . '  FROM user_book ub'
            . '  WHERE ub.user_id IN (SELECT user_target FROM user_following WHERE user_source = :userId)'
            . '    AND (ub.liked = 1 OR ub.has_read = 1 OR ub.owns_physically = 1)'
            . '  UNION ALL'
            . '  SELECT b.id AS book_id, b.created_at AS ts'
            . '  FROM book b'
            . '  WHERE b.added_by_id IN (SELECT user_target FROM user_following WHERE user_source = :userId)'
            . ') AS sub ';

        $params = ['userId' => $userId];
        $types  = [];

        if (!empty($excludeIds)) {
            $sql .= 'WHERE book_id NOT IN (:excludeIds) ';
            $params['excludeIds'] = $excludeIds;
            $types['excludeIds']  = \Doctrine\DBAL\ArrayParameterType::INTEGER;
        }

        $sql .= 'GROUP BY book_id ORDER BY last_ts DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $pageIds = array_map('intval', array_column(
            $conn->executeQuery($sql, $params, $types)->fetchAllAssociative(),
            'id'
        ));

        if (empty($pageIds)) {
            return [];
        }

        // Hydration DQL con JOIN per evitare query N+1
        $books = $this->createQueryBuilder('b')
            ->leftJoin('b.addedBy', 'a')->addSelect('a')
            ->leftJoin('b.genres', 'g')->addSelect('g')
            ->leftJoin('b.photos', 'p')->addSelect('p')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $pageIds)
            ->getQuery()
            ->getResult();

        // Ripristina l'ordine della query SQL
        $idOrder = array_flip($pageIds);
        usort($books, fn($a, $b) => $idOrder[$a->getId()] <=> $idOrder[$b->getId()]);

        return $books;
    }

    /**
     * Trova i libri fisicamente posseduti da utenti entro un certo raggio di distanza.
     * Usa la formula di Haversine in SQL per calcolare la distanza geodetica
     * dalla posizione di riferimento ($lat, $lng).
     *
     * @return array<int, array{book: Book, owner: User, distance: float}>
     */
    public function findNearbyLendableBooks(float $lat, float $lng, float $radiusKm = 25.0, int $limit = 50): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Formula di Haversine approssimata con ACOS (valida per distanze < 200 km)
        $sql = '
            SELECT ub.book_id, ub.user_id,
                (6371 * ACOS(
                    LEAST(1, COS(RADIANS(:lat)) * COS(RADIANS(u.latitude))
                    * COS(RADIANS(u.longitude) - RADIANS(:lng))
                    + SIN(RADIANS(:lat)) * SIN(RADIANS(u.latitude)))
                )) AS distance
            FROM user_book ub
            INNER JOIN `user` u ON u.id = ub.user_id
            WHERE ub.owns_physically = 1
              AND u.latitude IS NOT NULL
              AND u.longitude IS NOT NULL
            HAVING distance <= :radius
            ORDER BY distance ASC
            LIMIT :limit
        ';

        $rows = $conn->executeQuery($sql, [
            'lat'    => $lat,
            'lng'    => $lng,
            'radius' => $radiusKm,
            'limit'  => $limit,
        ], [
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
        ])->fetchAllAssociative();

        if (empty($rows)) {
            return [];
        }

        // Carica le entità libro e utente in due query batch
        $bookIds = array_unique(array_column($rows, 'book_id'));
        $userIds = array_unique(array_column($rows, 'user_id'));

        $books = $this->createQueryBuilder('b')
            ->leftJoin('b.genres', 'g')->addSelect('g')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', array_map('intval', $bookIds))
            ->getQuery()
            ->getResult();

        $bookMap = [];
        foreach ($books as $b) {
            $bookMap[$b->getId()] = $b;
        }

        $userRepo = $this->getEntityManager()->getRepository(User::class);
        $users    = $userRepo->findBy(['id' => array_map('intval', $userIds)]);
        $userMap  = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = $u;
        }

        // Combina i risultati SQL con le entità idratate
        $results = [];
        foreach ($rows as $row) {
            $bid = (int) $row['book_id'];
            $uid = (int) $row['user_id'];
            if (isset($bookMap[$bid], $userMap[$uid])) {
                $results[] = [
                    'book'     => $bookMap[$bid],
                    'owner'    => $userMap[$uid],
                    'distance' => round((float) $row['distance'], 1),
                ];
            }
        }

        return $results;
    }
}
