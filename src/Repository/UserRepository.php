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

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    /** @return User[] */
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

    /** Conta followers e following con query COUNT (niente hydration) */
    public function getProfileCounts(User $user): array
    {
        return $this->cache->get('profile_counts_' . $user->getId(), function (ItemInterface $item) use ($user) {
            $item->expiresAfter(120);

            $conn = $this->getEntityManager()->getConnection();
            $id = $user->getId();

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

    public function invalidateProfileCounts(User $user): void
    {
        $this->cache->delete('profile_counts_' . $user->getId());
    }

    /** Restituisce gli ID degli utenti seguiti (niente hydration) */
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

    public function invalidateFollowingIds(User $user): void
    {
        $this->cache->delete('following_ids_' . $user->getId());
    }

    /**
     * Trova libri che matchano la ricerca, con lista di utenti che li possiedono.
     * Raggruppato per LIBRO, non per utente.
     * 
     * @return array Array of ['book' => Book, 'owners' => [['user' => User, 'distance' => float]]]
     */
    public function findNearbyUsersWithBook(
        float $lat,
        float $lng,
        float $radiusKm = 25.0,
        string $bookSearch = '',
        int $limit = 100
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        // Trova libri e i loro proprietari con distanza
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
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radiusKm,
            'search' => '%' . $bookSearch . '%',
        ];

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        if (empty($rows)) {
            return [];
        }

        // Carica entità complete
        $bookIds = array_unique(array_column($rows, 'book_id'));
        $userIds = array_unique(array_column($rows, 'user_id'));

        // Carica libri
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

        // Carica utenti
        $users = $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->getId()] = $user;
        }

        // Raggruppa per libro
        $bookGroups = [];
        foreach ($rows as $row) {
            $bookId = $row['book_id'];
            
            if (!isset($bookGroups[$bookId])) {
                $bookGroups[$bookId] = [
                    'book' => $booksById[$bookId] ?? null,
                    'owners' => []
                ];
            }

            $bookGroups[$bookId]['owners'][] = [
                'user' => $usersById[$row['user_id']] ?? null,
                'distance' => round((float) $row['distance'], 1)
            ];
        }

        // Rimuovi libri senza entità valida
        $result = array_filter($bookGroups, fn($item) => $item['book'] !== null);

        return array_values($result);
    }
}
