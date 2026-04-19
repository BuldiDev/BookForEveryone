<?php

namespace App\Entity;

use App\Repository\LoanRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Richiesta di prestito libro tra utenti — stile Vinted.
 *
 * Flusso stati:
 *   pending   → C'è un'offerta attiva (da requester o owner). Chi NON ha fatto
 *               l'ultima offerta può accettare, controproporre o rifiutare.
 *   accepted  → Affare concluso; i due si accordano in chat per l'incontro.
 *               Entrambi confermano lo scambio fisico (requesterConfirmed / ownerConfirmed).
 *   completed → Scambio completato: punti trasferiti, libro rimosso dalla mappa.
 *   declined  → Uno dei due ha rifiutato.
 *   cancelled → Uno dei due ha annullato dopo l'accettazione.
 */
#[ORM\Entity(repositoryClass: LoanRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanRequest
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $requester = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** Punti dell'offerta attuale */
    #[ORM\Column(nullable: true)]
    private ?int $offeredPoints = null;

    /** Chi ha fatto l'ultima offerta: 'requester' o 'owner' */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastOfferBy = null;

    /** Conferma scambio fisico avvenuto */
    #[ORM\Column]
    private bool $requesterConfirmed = false;

    #[ORM\Column]
    private bool $ownerConfirmed = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }

    public function getRequester(): ?User { return $this->requester; }
    public function setRequester(?User $user): static { $this->requester = $user; return $this; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $user): static { $this->owner = $user; return $this; }

    public function getBook(): ?Book { return $this->book; }
    public function setBook(?Book $book): static { $this->book = $book; return $this; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getOfferedPoints(): ?int { return $this->offeredPoints; }
    public function setOfferedPoints(?int $points): static { $this->offeredPoints = $points; return $this; }

    public function getLastOfferBy(): ?string { return $this->lastOfferBy; }
    public function setLastOfferBy(?string $by): static { $this->lastOfferBy = $by; return $this; }

    public function isRequesterConfirmed(): bool { return $this->requesterConfirmed; }
    public function setRequesterConfirmed(bool $v): static { $this->requesterConfirmed = $v; return $this; }

    public function isOwnerConfirmed(): bool { return $this->ownerConfirmed; }
    public function setOwnerConfirmed(bool $v): static { $this->ownerConfirmed = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function isRequester(User $user): bool { return $this->requester?->getId() === $user->getId(); }
    public function isOwner(User $user): bool { return $this->owner?->getId() === $user->getId(); }

    /** L'utente corrente è chi ha fatto l'ultima offerta? */
    public function isLastOfferByUser(User $user): bool
    {
        if ($this->lastOfferBy === 'requester') return $this->isRequester($user);
        if ($this->lastOfferBy === 'owner') return $this->isOwner($user);
        return false;
    }

    /** Lo scambio è ancora attivo? */
    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_DECLINED, self::STATUS_CANCELLED], true);
    }
}
