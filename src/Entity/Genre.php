<?php

namespace App\Entity;

use App\Repository\GenreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Genere letterario (es. Fantascienza, Romance, Thriller).
 * Ogni genere è identificato da un nome univoco e da uno slug URL-friendly.
 * Viene usato sia per classificare i libri sia per personalizzare il feed degli utenti.
 */
#[ORM\Entity(repositoryClass: GenreRepository::class)]
class Genre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nome del genere visualizzato nell'interfaccia (es. "Fantascienza") */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    /** Versione URL-friendly del nome (es. "fantascienza"), usata per filtri e routing */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    /** Libri associati a questo genere */
    /** @var Collection<int, Book> */
    #[ORM\ManyToMany(targetEntity: Book::class, mappedBy: 'genres')]
    private Collection $books;

    /** Utenti che hanno selezionato questo genere come preferito */
    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'preferredGenres')]
    private Collection $users;

    public function __construct()
    {
        $this->books = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    /** @return Collection<int, Book> */
    public function getBooks(): Collection { return $this->books; }

    /** @return Collection<int, User> */
    public function getUsers(): Collection { return $this->users; }

    public function __toString(): string { return $this->name ?? ''; }
}
