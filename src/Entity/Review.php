<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Recensione scritta da un utente per un libro.
 * Ogni utente può lasciare una sola recensione per libro (vincolo univoco su user+book).
 * Il voto è un intero da 1 a 5 stelle; il testo è opzionale.
 * Se l'utente aggiorna la recensione, viene sovrascritta quella precedente.
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_book_review', columns: ['user_id', 'book_id'])]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Voto da 1 a 5 stelle (validato nel setter) */
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $rating = null;

    /** Testo facoltativo della recensione */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    /** Utente che ha scritto la recensione */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** Libro recensito */
    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    /** Data e ora di creazione della recensione */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Data e ora dell'ultimo aggiornamento */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRating(): ?int { return $this->rating; }

    /**
     * Imposta il voto.
     * @throws \InvalidArgumentException se il voto è fuori dall'intervallo 1-5
     */
    public function setRating(int $rating): static
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Il voto deve essere tra 1 e 5.');
        }
        $this->rating = $rating;
        return $this;
    }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): static { $this->content = $content; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getBook(): ?Book { return $this->book; }
    public function setBook(?Book $book): static { $this->book = $book; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /** Aggiorna automaticamente il timestamp prima di ogni UPDATE su database */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
