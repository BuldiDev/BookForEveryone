<?php

namespace App\Repository;

use App\Entity\Genre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità Genre.
 *
 * I generi sono dati semi-statici (raramente modificati) popolati tramite fixture.
 * Ogni genere ha uno slug univoco usato per la ricerca e per i filtri nel feed.
 *
 * @extends ServiceEntityRepository<Genre>
 */
class GenreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Genre::class);
    }

    /**
     * Restituisce tutti i generi in ordine alfabetico.
     * Usato nella pagina di onboarding e nei form di selezione generi.
     *
     * @return Genre[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trova un genere tramite il suo slug univoco.
     * Usato per applicare i filtri del feed dalla URL (es. /feed?genre=fantasy).
     */
    public function findBySlug(string $slug): ?Genre
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
