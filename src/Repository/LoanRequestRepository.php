<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\LoanRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanRequest>
 */
class LoanRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanRequest::class);
    }

    /**
     * Richiesta attiva (non completata/rifiutata) legata a una conversazione.
     */
    public function findActiveByConversation(Conversation $conversation): ?LoanRequest
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.conversation = :conv')
            ->andWhere('lr.status NOT IN (:terminal)')
            ->setParameter('conv', $conversation)
            ->setParameter('terminal', [LoanRequest::STATUS_COMPLETED, LoanRequest::STATUS_REJECTED])
            ->orderBy('lr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Ultima richiesta (qualsiasi stato) per una conversazione.
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
     * Verifica se esiste già una richiesta attiva tra due utenti per lo stesso libro.
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
            ->setParameter('terminal', [LoanRequest::STATUS_COMPLETED, LoanRequest::STATUS_REJECTED])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
