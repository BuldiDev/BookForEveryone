<?php

namespace App\Repository;

use App\Entity\UserBook;
use App\Entity\User;
use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBook>
 */
class UserBookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBook::class);
    }

    public function findByUserAndBook(User $user, Book $book): ?UserBook
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /**
     * Batch-load UserBook per un utente e più libri in una singola query.
     * @param int[] $bookIds
     * @return array<int, UserBook|null> Indicizzato per book ID
     */
    public function findByUserForBooks(User $user, array $bookIds): array
    {
        if (empty($bookIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('ub')
            ->join('ub.book', 'b')
            ->where('ub.user = :user')
            ->andWhere('b.id IN (:bookIds)')
            ->setParameter('user', $user)
            ->setParameter('bookIds', $bookIds)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($results as $ub) {
            $indexed[$ub->getBook()->getId()] = $ub;
        }

        return $indexed;
    }

    /**
     * Trova o crea la relazione UserBook.
     */
    public function findOrCreate(User $user, Book $book): UserBook
    {
        $userBook = $this->findByUserAndBook($user, $book);

        if (!$userBook) {
            $userBook = new UserBook();
            $userBook->setUser($user);
            $userBook->setBook($book);
        }

        return $userBook;
    }

    /** Tutti i libri letti da un utente */
    public function findReadByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.book', 'b')->addSelect('b')
            ->where('ub.user = :user')
            ->andWhere('ub.hasRead = true')
            ->setParameter('user', $user)
            ->orderBy('ub.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Tutti i libri posseduti fisicamente */
    public function findOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.book', 'b')->addSelect('b')
            ->where('ub.user = :user')
            ->andWhere('ub.ownsPhysically = true')
            ->setParameter('user', $user)
            ->orderBy('ub.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Tutti i libri con like */
    public function findLikedByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.book', 'b')
            ->where('ub.user = :user')
            ->andWhere('ub.liked = true')
            ->setParameter('user', $user)
            ->orderBy('ub.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
