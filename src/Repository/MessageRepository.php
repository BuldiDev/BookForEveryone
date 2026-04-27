<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità Message.
 *
 * Gestisce il recupero dei messaggi di una conversazione.
 * I messaggi sono ordinati in senso cronologico ascendente per essere
 * visualizzati correttamente nella chat (dal più vecchio al più recente).
 *
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Restituisce gli ultimi N messaggi di una conversazione, ordinati dal più vecchio al più recente.
     * Il limit evita di caricare l'intera storia della chat in sessioni molto lunghe.
     *
     * @return Message[]
     */
    public function findByConversation(Conversation $conversation, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
