<?php

namespace App\Entity;

use App\Repository\BookPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Foto di un libro caricata da un utente.
 * Può rappresentare la copia personale, il libro su uno scaffale, ecc.
 * Le foto sono ordinate per posizione nella galleria del libro.
 */
#[ORM\Entity(repositoryClass: BookPhotoRepository::class)]
class BookPhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Percorso relativo dell'immagine (es. /uploads/books/foto-123.jpg) */
    #[ORM\Column(length: 255)]
    private ?string $imageUrl = null;

    /** Didascalia opzionale inserita dall'utente */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    /** Posizione nella galleria del libro (0 = prima foto) */
    #[ORM\Column]
    private int $position = 0;

    /** Libro a cui appartiene questa foto */
    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    /** Utente che ha caricato la foto */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploadedBy = null;

    /** Data e ora di caricamento */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }

    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $caption): static { $this->caption = $caption; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getBook(): ?Book { return $this->book; }
    public function setBook(?Book $book): static { $this->book = $book; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $uploadedBy): static { $this->uploadedBy = $uploadedBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
