<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository per l'entità User.
 *
 * Implementa PasswordUpgraderInterface per aggiornare automaticamente
 * l'hash della password quando Symfony rileva un algoritmo obsoleto.
 *
 * I contatori follower/following e la lista degli ID seguiti sono cachati
 * in Redis per 120 secondi, perché vengono letti ad ogni rendering del feed.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private CacheInterface $cache,
    ) {
        parent::__construct($registry, User::class);
    }

    /**
     * Aggiorna l'hash della password nel database.
     * Chiamato da Symfony quando viene rilevato un algoritmo di hashing obsoleto.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Istanze di "%s" non sono supportate.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trova un utente per username (case-sensitive).
     */
    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Ricerca utenti per username o nome visualizzato (ricerca parziale LIKE).
     *
     * @return User[]
     */
    public function searchUsers(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.displayName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Conta follower e following tramite query COUNT nativa (senza hydration).
     * Cachato in Redis per 120 secondi per ridurre il carico sul database.
     *
     * @return array{followers: int, following: int}
     */
    public function getProfileCounts(User $user): array
    {
        return $this->cache->get('profile_counts_' . $user->getId(), function (ItemInterface $item) use ($user) {
            $item->expiresAfter(120);

            $conn = $this->getEntityManager()->getConnection();
            $id   = $user->getId();

            $followersCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM user_following WHERE user_target = :id',
                ['id' => $id]
            );
            $followingCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM user_following WHERE user_source = :id',
                ['id' => $id]
            );

            return ['followers' => $followersCount, 'following' => $followingCount];
        });
    }

    /**
     * Invalida la cache dei contatori di un profilo.
     * Chiamato dopo ogni operazione di follow/unfollow.
     */
    public function invalidateProfileCounts(User $user): void
    {
        $this->cache->delete('profile_counts_' . $user->getId());
    }

    /**
     * Restituisce gli ID degli utenti seguiti senza hydration (solo scalari).
     * Cachato in Redis per 120 secondi; usato in ogni rendering del feed
     * per mostrare il pulsante "Segui / Seguendo" sulle card.
     *
     * @return int[]
     */
    public function getFollowingIds(User $user): array
    {
        return $this->cache->get('following_ids_' . $user->getId(), function (ItemInterface $item) use ($user) {
            $item->expiresAfter(120);

            $conn = $this->getEntityManager()->getConnection();
            $rows = $conn->fetchAllAssociative(
                'SELECT user_target FROM user_following WHERE user_source = :id',
                ['id' => $user->getId()]
            );
            return array_map(fn($r) => (int) $r['user_target'], $rows);
        });
    }

    /**
     * Invalida la cache degli ID seguiti.
     * Chiamato dopo ogni operazione di follow/unfollow.
     */
    public function invalidateFollowingIds(User $user): void
    {
        $this->cache->delete('following_ids_' . $user->getId());
    }

    /**
     * Trova gli utenti vicini che possiedono fisicamente un libro che corrisponde alla ricerca.
     * Usa la formula di Haversine in SQL per calcolare la distanza.
     * I risultati sono raggruppati per libro, con la lista degli utenti proprietari.
     *
     * @return array<int, array{book: \App\Entity\Book, owners: array<int, array{user: User, distance: float}>}>
     */
    public function findNearbyUsersWithBook(
        float $lat,
        float $lng,
        float $radiusKm = 25.0,
        string $bookSearch = '',
        int $limit = 100
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        // Query con Haversine per distanza + filtro titolo libro
        $sql = '
            SELECT
                b.id as book_id,
                b.title as book_title,
                b.book_author as book_author,
                b.cover_url as book_cover,
                u.id as user_id,
                u.username,
                u.display_name,
                u.avatar_url,
                u.latitude,
                u.longitude,
                (6371 * ACOS(
                    LEAST(1, COS(RADIANS(:lat)) * COS(RADIANS(u.latitude))
                    * COS(RADIANS(u.longitude) - RADIANS(:lng))
                    + SIN(RADIANS(:lat)) * SIN(RADIANS(u.latitude)))
                )) AS distance
            FROM book b
            INNER JOIN user_book ub ON ub.book_id = b.id
            INNER JOIN `user` u ON u.id = ub.user_id
            WHERE ub.owns_physically = 1
              AND u.latitude IS NOT NULL
              AND u.longitude IS NOT NULL
              AND b.title LIKE :search
            HAVING distance <= :radius
            ORDER BY b.title ASC, distance ASC
        ';

        $params = [
            'lat'    => $lat,
            'lng'    => $lng,
            'radius' => $radiusKm,
            'search' => '%' . $bookSearch . '%',
        ];

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        if (empty($rows)) {
            return [];
        }

        // Carica le entità complete in due query batch
        $bookIds = array_unique(array_column($rows, 'book_id'));
        $userIds = array_unique(array_column($rows, 'user_id'));

        $books = $this->getEntityManager()->getRepository('App\Entity\Book')
            ->createQueryBuilder('b')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $bookIds)
            ->getQuery()
            ->getResult();

        $booksById = [];
        foreach ($books as $book) {
            $booksById[$book->getId()] = $book;
        }

        $users = $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->getId()] = $user;
        }

        // Raggruppa per libro: ogni libro ha la lista dei proprietari con distanza
        $bookGroups = [];
        foreach ($rows as $row) {
            $bookId = $row['book_id'];

            if (!isset($bookGroups[$bookId])) {
                $bookGroups[$bookId] = [
                    'book'   => $booksById[$bookId] ?? null,
                    'owners' => [],
                ];
            }

            $bookGroups[$bookId]['owners'][] = [
                'user'     => $usersById[$row['user_id']] ?? null,
                'distance' => round((float) $row['distance'], 1),
            ];
        }

        // Rimuove eventuali voci senza entità valida (race condition tra SQL e hydration)
        $result = array_filter($bookGroups, fn($item) => $item['book'] !== null);

        return array_values($result);
    }
}
