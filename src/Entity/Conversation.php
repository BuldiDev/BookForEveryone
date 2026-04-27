<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversazione privata tra due utenti della piattaforma.
 * Ogni conversazione contiene una sequenza di messaggi ordinati cronologicamente
 * e può essere associata a una richiesta di prestito attiva.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Data e ora di creazione della conversazione */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Data e ora dell'ultimo messaggio (aggiornata automaticamente) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** I due utenti partecipanti alla conversazione */
    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'conversation_participant')]
    private Collection $participants;

    /** Sequenza di messaggi, ordinata per data crescente */
    /** @var Collection<int, Message> */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt    = new \DateTimeImmutable();
        $this->participants = new ArrayCollection();
        $this->messages     = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /** Aggiorna il timestamp prima di ogni UPDATE su database */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** @return Collection<int, User> */
    public function getParticipants(): Collection { return $this->participants; }

    /** Aggiunge un partecipante alla conversazione (evita duplicati) */
    public function addParticipant(User $user): static
    {
        if (!$this->participants->contains($user)) {
            $this->participants->add($user);
        }
        return $this;
    }

    /** Verifica se un utente è partecipante della conversazione */
    public function hasParticipant(User $user): bool
    {
        return $this->participants->contains($user);
    }

    /** Restituisce l'altro partecipante rispetto all'utente passato come parametro */
    public function getOtherParticipant(User $me): ?User
    {
        foreach ($this->participants as $p) {
            if ($p->getId() !== $me->getId()) {
                return $p;
            }
        }
        return null;
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }

    /** Aggiunge un messaggio alla conversazione e imposta il collegamento inverso */
    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }
        return $this;
    }

    /** Restituisce l'ultimo messaggio della conversazione, utile per l'anteprima nella lista chat */
    public function getLastMessage(): ?Message
    {
        if ($this->messages->isEmpty()) {
            return null;
        }
        return $this->messages->last() ?: null;
    }
}
