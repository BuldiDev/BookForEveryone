<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Trova la conversazione 1-a-1 tra due utenti, se esiste.
     */
    public function findBetween(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p1')
            ->innerJoin('c.participants', 'p2')
            ->where('p1 = :a')
            ->andWhere('p2 = :b')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Tutte le conversazioni di un utente, ordinate per ultimo messaggio.
     * @return Conversation[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->leftJoin('c.messages', 'm')
            ->where('p = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('MAX(m.createdAt)', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
