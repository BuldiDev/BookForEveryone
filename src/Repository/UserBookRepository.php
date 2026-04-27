<?php

namespace App\Repository;

use App\Entity\UserBook;
use App\Entity\User;
use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità UserBook, che registra le interazioni
 * (like, letto, possiedo fisicamente) tra un utente e un libro.
 *
 * @extends ServiceEntityRepository<UserBook>
 */
class UserBookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBook::class);
    }

    /**
     * Trova il record di interazione tra un utente e un libro specifico.
     * Restituisce null se l'utente non ha ancora interagito con quel libro.
     */
    public function findByUserAndBook(User $user, Book $book): ?UserBook
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /**
     * Carica in batch le interazioni di un utente per un set di libri con una singola query.
     * Evita N query nel loop di rendering delle card del feed.
     *
     * @param int[] $bookIds
     * @return array<int, UserBook> Indicizzato per book_id
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

        // Indicizza per book_id per un accesso O(1) nel template
        $indexed = [];
        foreach ($results as $ub) {
            $indexed[$ub->getBook()->getId()] = $ub;
        }

        return $indexed;
    }

    /**
     * Trova il record di interazione o ne crea uno nuovo se non esiste ancora.
     * Usato nelle azioni rapide (like, letto, possiedo) per garantire la presenza del record.
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

    /**
     * Restituisce tutti i libri segnati come letti da un utente, ordinati per data.
     * Usato nella sezione "Libri letti" del profilo pubblico.
     *
     * @return UserBook[]
     */
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

    /**
     * Restituisce tutti i libri posseduti fisicamente da un utente.
     * Usato nella sezione "Libri posseduti" del profilo e nella ricerca prestiti.
     *
     * @return UserBook[]
     */
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

    /**
     * Restituisce tutti i libri a cui l'utente ha messo "mi piace".
     *
     * @return UserBook[]
     */
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
