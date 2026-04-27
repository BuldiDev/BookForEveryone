<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entità principale che rappresenta un libro nel catalogo della piattaforma.
 *
 * Un libro viene aggiunto da un utente tramite ricerca ISBN e può essere:
 * - messo "mi piace" da altri utenti
 * - segnato come letto
 * - posseduto fisicamente (disponibile al prestito)
 * - recensito con un voto da 1 a 5 stelle
 * - corredato di foto caricate dagli utenti
 */
#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Titolo del libro */
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /** Nome dell'autore principale */
    #[ORM\Column(length: 255)]
    private ?string $bookAuthor = null;

    /** Trama o descrizione estesa del libro */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Codice ISBN-13 del libro (solo cifre, senza trattini) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $isbn = null;

    /** URL della copertina (caricata dall'utente o recuperata dall'API) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverUrl = null;

    /** Numero totale di pagine */
    #[ORM\Column(nullable: true)]
    private ?int $pageCount = null;

    /** Nome dell'editore */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $publisher = null;

    /** Anno di pubblicazione */
    #[ORM\Column(nullable: true)]
    private ?int $publicationYear = null;

    /** Utente che ha aggiunto questo libro al catalogo */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $addedBy = null;

    /** Data e ora di inserimento nel database */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Data e ora dell'ultimo aggiornamento (aggiornata automaticamente da Doctrine) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Generi letterari associati al libro (relazione molti-a-molti) */
    /** @var Collection<int, Genre> */
    #[ORM\ManyToMany(targetEntity: Genre::class, inversedBy: 'books')]
    #[ORM\JoinTable(name: 'book_genre')]
    private Collection $genres;

    /** Foto caricate dagli utenti per questo libro, ordinate per posizione */
    /** @var Collection<int, BookPhoto> */
    #[ORM\OneToMany(targetEntity: BookPhoto::class, mappedBy: 'book', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $photos;

    /** Recensioni degli utenti, ordinate per data decrescente */
    /** @var Collection<int, Review> */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $reviews;

    /** Interazioni degli utenti con il libro (like, letto, possiedo) */
    /** @var Collection<int, UserBook> */
    #[ORM\OneToMany(targetEntity: UserBook::class, mappedBy: 'book', orphanRemoval: true)]
    private Collection $userBooks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->genres    = new ArrayCollection();
        $this->photos    = new ArrayCollection();
        $this->reviews   = new ArrayCollection();
        $this->userBooks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getBookAuthor(): ?string { return $this->bookAuthor; }
    public function setBookAuthor(string $bookAuthor): static { $this->bookAuthor = $bookAuthor; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getIsbn(): ?string { return $this->isbn; }
    public function setIsbn(?string $isbn): static { $this->isbn = $isbn; return $this; }

    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function setCoverUrl(?string $coverUrl): static { $this->coverUrl = $coverUrl; return $this; }

    public function getPageCount(): ?int { return $this->pageCount; }
    public function setPageCount(?int $pageCount): static { $this->pageCount = $pageCount; return $this; }

    public function getPublisher(): ?string { return $this->publisher; }
    public function setPublisher(?string $publisher): static { $this->publisher = $publisher; return $this; }

    public function getPublicationYear(): ?int { return $this->publicationYear; }
    public function setPublicationYear(?int $publicationYear): static { $this->publicationYear = $publicationYear; return $this; }

    public function getAddedBy(): ?User { return $this->addedBy; }
    public function setAddedBy(?User $addedBy): static { $this->addedBy = $addedBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /** Aggiorna automaticamente il timestamp di modifica prima di ogni UPDATE su database */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, Genre> */
    public function getGenres(): Collection { return $this->genres; }

    public function addGenre(Genre $genre): static
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
        }
        return $this;
    }

    public function removeGenre(Genre $genre): static
    {
        $this->genres->removeElement($genre);
        return $this;
    }

    /** @return Collection<int, BookPhoto> */
    public function getPhotos(): Collection { return $this->photos; }

    public function addPhoto(BookPhoto $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setBook($this);
        }
        return $this;
    }

    public function removePhoto(BookPhoto $photo): static
    {
        if ($this->photos->removeElement($photo)) {
            if ($photo->getBook() === $this) {
                $photo->setBook(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }

    /** @return Collection<int, UserBook> */
    public function getUserBooks(): Collection { return $this->userBooks; }

    /** Calcola la media aritmetica dei voti, arrotondata a una cifra decimale */
    public function getAverageRating(): ?float
    {
        if ($this->reviews->isEmpty()) {
            return null;
        }
        $total = 0;
        foreach ($this->reviews as $review) {
            $total += $review->getRating();
        }
        return round($total / $this->reviews->count(), 1);
    }

    /** Numero totale di recensioni ricevute */
    public function getReviewsCount(): int { return $this->reviews->count(); }

    /** Numero di utenti che hanno messo "mi piace" al libro */
    public function getLikesCount(): int
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->isLiked())->count();
    }

    /** Numero di utenti che hanno segnato il libro come letto */
    public function getReadersCount(): int
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->hasRead())->count();
    }

    public function __toString(): string { return $this->title ?? ''; }
}
