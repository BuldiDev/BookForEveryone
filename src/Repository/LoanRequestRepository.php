<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\LoanRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità LoanRequest (richieste di prestito).
 *
 * Una richiesta è considerata "attiva" finché non raggiunge uno stato terminale
 * (completed, declined o cancelled).
 *
 * @extends ServiceEntityRepository<LoanRequest>
 */
class LoanRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanRequest::class);
    }

    /**
     * Trova la richiesta di prestito ancora attiva associata a una conversazione.
     * Usato per visualizzare la barra di azioni nella chat.
     * Restituisce null se tutte le richieste della conversazione sono in stato terminale.
     */
    public function findActiveByConversation(Conversation $conversation): ?LoanRequest
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.conversation = :conv')
            ->andWhere('lr.status NOT IN (:terminal)')
            ->setParameter('conv', $conversation)
            ->setParameter('terminal', [LoanRequest::STATUS_COMPLETED, LoanRequest::STATUS_DECLINED, LoanRequest::STATUS_CANCELLED])
            ->orderBy('lr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trova l'ultima richiesta di prestito per una conversazione, indipendentemente dallo stato.
     * Usato per mostrare lo storico delle trattative nella chat.
     */
    public function findLatestByConversation(Conversation $conversation): ?LoanRequest
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('lr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Verifica se esiste già una richiesta attiva tra un richiedente e un proprietario
     * per lo stesso libro, evitando richieste duplicate.
     */
    public function findActiveBetweenForBook(User $requester, User $owner, int $bookId): ?LoanRequest
    {
        return $this->createQueryBuilder('lr')
            ->join('lr.book', 'b')
            ->where('lr.requester = :req')
            ->andWhere('lr.owner = :owner')
            ->andWhere('b.id = :bookId')
            ->andWhere('lr.status NOT IN (:terminal)')
            ->setParameter('req', $requester)
            ->setParameter('owner', $owner)
            ->setParameter('bookId', $bookId)
            ->setParameter('terminal', [LoanRequest::STATUS_COMPLETED, LoanRequest::STATUS_DECLINED, LoanRequest::STATUS_CANCELLED])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
