<?php

namespace App\Service;

use App\Entity\User;
use Predis\Client;

/**
 * Servizio per la gestione della cronologia del feed tramite Redis.
 *
 * Tiene traccia dei libri già mostrati a ciascun utente usando un Set Redis
 * per evitare di riproporre gli stessi titoli durante lo scroll infinito.
 * La cronologia ha una scadenza di 30 giorni; quando è esaurita il FeedController
 * la resetta e ricomincia dal principio.
 *
 * Struttura dati Redis:
 *   Chiave: "user:{id}:seen_books"
 *   Tipo:   Set di interi (ID dei libri visti)
 *   TTL:    30 giorni
 */
class FeedHistoryService
{
    private Client $redis;

    public function __construct(string $redisUrl)
    {
        $this->redis = new Client($redisUrl);
    }

    /**
     * Aggiunge un singolo libro all'insieme dei libri già visti dall'utente.
     */
    public function markBookAsSeen(User $user, int $bookId): void
    {
        $key = $this->getSeenBooksKey($user);
        $this->redis->sadd($key, [$bookId]);

        // Rinnova la scadenza a 30 giorni ad ogni aggiornamento
        $this->redis->expire($key, 60 * 60 * 24 * 30);
    }

    /**
     * Aggiunge un batch di libri all'insieme dei libri già visti.
     * Usato dopo ogni caricamento di pagina nel feed per segnare tutti i libri mostrati.
     *
     * @param int[] $bookIds
     */
    public function markBooksAsSeen(User $user, array $bookIds): void
    {
        if (empty($bookIds)) {
            return;
        }

        $key = $this->getSeenBooksKey($user);
        $this->redis->sadd($key, $bookIds);

        // Rinnova la scadenza a 30 giorni ad ogni aggiornamento
        $this->redis->expire($key, 60 * 60 * 24 * 30);
    }

    /**
     * Restituisce tutti gli ID dei libri già mostrati all'utente.
     * Usato dal repository per escluderli dalla query del feed.
     *
     * @return int[]
     */
    public function getSeenBookIds(User $user): array
    {
        $key = $this->getSeenBooksKey($user);
        $ids = $this->redis->smembers($key);

        return array_map('intval', $ids);
    }

    /**
     * Verifica se un singolo libro è già stato mostrato all'utente.
     */
    public function hasSeenBook(User $user, int $bookId): bool
    {
        $key = $this->getSeenBooksKey($user);
        return (bool) $this->redis->sismember($key, $bookId);
    }

    /**
     * Azzera la cronologia del feed dell'utente.
     * Chiamato quando tutti i libri disponibili sono già stati mostrati
     * e il feed deve ricominciare dall'inizio.
     */
    public function resetHistory(User $user): void
    {
        $key = $this->getSeenBooksKey($user);
        $this->redis->del([$key]);
    }

    /**
     * Costruisce la chiave Redis per i libri visti da un determinato utente.
     */
    private function getSeenBooksKey(User $user): string
    {
        return sprintf('user:%d:seen_books', $user->getId());
    }
}
