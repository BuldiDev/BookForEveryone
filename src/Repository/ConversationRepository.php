<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità Conversation.
 *
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Trova la conversazione privata tra due utenti specifici, se esiste.
     * Usato prima di crearne una nuova per evitare duplicati.
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
     * Restituisce tutte le conversazioni dell'utente ordinate per ultimo messaggio.
     * Usato nella lista chat per mostrare le conversazioni più recenti in cima.
     *
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
