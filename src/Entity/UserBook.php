<?php

namespace App\Entity;

use App\Repository\UserBookRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Relazione User ↔ Book per le interazioni:
 *  - liked: cuore nel feed
 *  - hasRead: "Ho letto"
 *  - ownsPhysically: "Lo posseggo fisicamente"
 */
#[ORM\Entity(repositoryClass: UserBookRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_book', columns: ['user_id', 'book_id'])]
class UserBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userBooks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Book::class, inversedBy: 'userBooks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\Column(type: 'boolean')]
    private bool $liked = false;

    #[ORM\Column(name: 'has_read', type: 'boolean')]
    private bool $hasRead = false;

    #[ORM\Column(type: 'boolean')]
    private bool $ownsPhysically = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;
        return $this;
    }

    public function isLiked(): bool
    {
        return $this->liked;
    }

    public function setLiked(bool $liked): static
    {
        $this->liked = $liked;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function toggleLike(): static
    {
        $this->liked = !$this->liked;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function hasRead(): bool
    {
        return $this->hasRead;
    }

    public function setHasRead(bool $hasRead): static
    {
        $this->hasRead = $hasRead;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function toggleHasRead(): static
    {
        $this->hasRead = !$this->hasRead;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function ownsPhysically(): bool
    {
        return $this->ownsPhysically;
    }

    public function setOwnsPhysically(bool $ownsPhysically): static
    {
        $this->ownsPhysically = $ownsPhysically;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function toggleOwnsPhysically(): static
    {
        $this->ownsPhysically = !$this->ownsPhysically;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Controlla se c'è almeno un'interazione attiva */
    public function hasAnyInteraction(): bool
    {
        return $this->liked || $this->hasRead || $this->ownsPhysically;
    }
}
