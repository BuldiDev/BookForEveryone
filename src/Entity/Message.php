<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Messaggio all'interno di una conversazione.
 *
 * Un messaggio può essere di due nature:
 *  - "text": messaggio testuale libero scritto dall'utente
 *  - evento di prestito: messaggio generato automaticamente dal sistema durante
 *    il flusso di una richiesta di prestito (offerta, controproposta, accettazione, ecc.)
 *
 * Tipi di evento prestito supportati:
 *   loan_offer, loan_counter, loan_accepted, loan_declined,
 *   loan_cancelled, loan_confirm, loan_completed
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Conversazione a cui appartiene il messaggio */
    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    /** Utente che ha inviato il messaggio (o il sistema per gli eventi prestito) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    /** Testo del messaggio o etichetta sintetica dell'evento (es. "Offerta: 5 CP") */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    /** Tipo del messaggio: "text" per i messaggi normali, o codice evento per i prestiti */
    #[ORM\Column(length: 20, options: ['default' => 'text'])]
    private string $type = 'text';

    /** Richiesta di prestito collegata (null per i messaggi di testo normali) */
    #[ORM\ManyToOne(targetEntity: LoanRequest::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LoanRequest $loanRequest = null;

    /**
     * Dati snapshot dell'evento prestito (punti, titolo libro, copertina, ecc.).
     * Salvati al momento dell'evento per preservare la cronologia anche se il prestito cambia.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /** Data e ora di invio del messaggio */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): static { $this->sender = $sender; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getLoanRequest(): ?LoanRequest { return $this->loanRequest; }
    public function setLoanRequest(?LoanRequest $lr): static { $this->loanRequest = $lr; return $this; }

    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }

    /** Restituisce true se il messaggio è un evento di sistema legato a un prestito */
    public function isLoanEvent(): bool { return $this->type !== 'text'; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
