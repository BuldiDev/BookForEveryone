<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
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
        $conn = $this->getEntityManager()->getConnection();
        $id = $user->getId();

        // user_source segue user_target
        $followersCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM user_following WHERE user_target = :id',
            ['id' => $id]
        );
        $followingCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM user_following WHERE user_source = :id',
            ['id' => $id]
        );

        return ['followers' => $followersCount, 'following' => $followingCount];
    }

    /** Restituisce gli ID degli utenti seguiti (niente hydration) */
    public function getFollowingIds(User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT user_target FROM user_following WHERE user_source = :id',
            ['id' => $user->getId()]
        );
        return array_map(fn($r) => (int) $r['user_target'], $rows);
    }
}
