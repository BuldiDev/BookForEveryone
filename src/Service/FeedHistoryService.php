<?php

namespace App\Service;

use App\Entity\User;
use Predis\Client;

class FeedHistoryService
{
    private Client $redis;

    public function __construct(string $redisUrl)
    {
        $this->redis = new Client($redisUrl);
    }

    /**
     * Segna un libro come visto dall'utente
     */
    public function markBookAsSeen(User $user, int $bookId): void
    {
        $key = $this->getSeenBooksKey($user);
        $this->redis->sadd($key, [$bookId]);
        
        // Imposta scadenza a 30 giorni
        $this->redis->expire($key, 60 * 60 * 24 * 30);
    }

    /**
     * Segna multipli libri come visti
     */
    public function markBooksAsSeen(User $user, array $bookIds): void
    {
        if (empty($bookIds)) {
            return;
        }

        $key = $this->getSeenBooksKey($user);
        $this->redis->sadd($key, $bookIds);
        
        // Imposta scadenza a 30 giorni
        $this->redis->expire($key, 60 * 60 * 24 * 30);
    }

    /**
     * Ottieni tutti gli ID dei libri già visti dall'utente
     */
    public function getSeenBookIds(User $user): array
    {
        $key = $this->getSeenBooksKey($user);
        $ids = $this->redis->smembers($key);
        
        return array_map('intval', $ids);
    }

    /**
     * Verifica se un libro è stato visto
     */
    public function hasSeenBook(User $user, int $bookId): bool
    {
        $key = $this->getSeenBooksKey($user);
        return (bool) $this->redis->sismember($key, $bookId);
    }

    /**
     * Resetta la cronologia dell'utente
     */
    public function resetHistory(User $user): void
    {
        $key = $this->getSeenBooksKey($user);
        $this->redis->del([$key]);
    }

    /**
     * Genera la chiave Redis per i libri visti
     */
    private function getSeenBooksKey(User $user): string
    {
        return sprintf('user:%d:seen_books', $user->getId());
    }
}
