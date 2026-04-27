<?php

namespace App\Repository;

use App\Entity\BookPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository per l'entità BookPhoto.
 *
 * Le foto aggiuntive dei libri sono gestite tramite l'entità BookPhoto,
 * che associa un'immagine a un libro e a un utente che l'ha caricata.
 * Il recupero delle foto avviene tramite la relazione OneToMany su Book,
 * quindi questo repository non espone metodi aggiuntivi oltre a quelli ereditati.
 *
 * @extends ServiceEntityRepository<BookPhoto>
 */
class BookPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookPhoto::class);
    }
}
