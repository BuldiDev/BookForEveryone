<?php

namespace App\Entity;

use App\Repository\LoanRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Richiesta di prestito libro tra utenti.
 *
 * Flusso stati:
 *   pending      → Il richiedente ha aperto la richiesta (-1 cultura point)
 *   proposed     → Il proprietario ha proposto X punti cultura
 *   awaiting_start → Il richiedente ha accettato la proposta; in attesa che ENTRAMBI confermino "Inizio Scambio"
 *   in_progress  → Entrambi hanno confermato inizio; libro rimosso dalla mappa
 *   awaiting_end → Attesa che ENTRAMBI confermino "Fine Scambio"
 *   completed    → Scambio completato; punti trasferiti, libro rimosso definitivamente
 *   rejected     → Uno dei due ha rifiutato
 */
#[ORM\Entity(repositoryClass: LoanRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanRequest
{
    public const STATUS_PENDING        = 'pending';
    public const STATUS_PROPOSED       = 'proposed';
    public const STATUS_AWAITING_START = 'awaiting_start';
    public const STATUS_IN_PROGRESS    = 'in_progress';
    public const STATUS_AWAITING_END   = 'awaiting_end';
    public const STATUS_COMPLETED      = 'completed';
    public const STATUS_REJECTED       = 'rejected';

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

    /** Punti proposti dal proprietario */
    #[ORM\Column(nullable: true)]
    private ?int $proposedPoints = null;

    /** Conferme per "Inizio Scambio" */
    #[ORM\Column]
    private bool $requesterAcceptedStart = false;

    #[ORM\Column]
    private bool $ownerAcceptedStart = false;

    /** Conferme per "Fine Scambio" */
    #[ORM\Column]
    private bool $requesterAcceptedEnd = false;

    #[ORM\Column]
    private bool $ownerAcceptedEnd = false;

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

    public function getProposedPoints(): ?int { return $this->proposedPoints; }
    public function setProposedPoints(?int $points): static { $this->proposedPoints = $points; return $this; }

    public function isRequesterAcceptedStart(): bool { return $this->requesterAcceptedStart; }
    public function setRequesterAcceptedStart(bool $v): static { $this->requesterAcceptedStart = $v; return $this; }

    public function isOwnerAcceptedStart(): bool { return $this->ownerAcceptedStart; }
    public function setOwnerAcceptedStart(bool $v): static { $this->ownerAcceptedStart = $v; return $this; }

    public function isRequesterAcceptedEnd(): bool { return $this->requesterAcceptedEnd; }
    public function setRequesterAcceptedEnd(bool $v): static { $this->requesterAcceptedEnd = $v; return $this; }

    public function isOwnerAcceptedEnd(): bool { return $this->ownerAcceptedEnd; }
    public function setOwnerAcceptedEnd(bool $v): static { $this->ownerAcceptedEnd = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /** L'utente corrente è il richiedente? */
    public function isRequester(User $user): bool { return $this->requester?->getId() === $user->getId(); }
    /** L'utente corrente è il proprietario? */
    public function isOwner(User $user): bool { return $this->owner?->getId() === $user->getId(); }

    /** Lo scambio è ancora attivo (non terminato né rifiutato) */
    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REJECTED], true);
    }
}
