<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entità che rappresenta un utente registrato alla piattaforma.
 *
 * Ogni utente dispone di:
 * - credenziali di accesso (email + password hashata)
 * - un profilo pubblico (username, nome visualizzato, biografia, avatar)
 * - Culture Points: valuta interna usata per le richieste di prestito
 * - posizione geografica (latitudine/longitudine) per la ricerca di prestatori vicini
 * - generi preferiti scelti durante l'onboarding
 * - relazioni social (seguiti e seguaci)
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Esiste già un account con questa email')]
#[UniqueEntity(fields: ['username'], message: 'Questo username è già in uso')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Indirizzo email (usato come identificatore per il login) */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /** Nome utente pubblico e unico sulla piattaforma */
    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    /** Ruoli Symfony (ROLE_USER sempre presente, ROLE_ADMIN se isAdmin = true) */
    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /** Password hashata con bcrypt/argon2 */
    #[ORM\Column]
    private ?string $password = null;

    /** Nome visualizzato nel feed e nel profilo (se null, si usa username) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $displayName = null;

    /** Breve biografia visibile nel profilo pubblico */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    /** URL dell'immagine profilo */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    /** Data e ora di registrazione */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Data e ora dell'ultimo aggiornamento del profilo */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Data e ora in cui l'utente ha prestato il consenso al trattamento dei dati
     * personali (inclusa la posizione geografica) durante la registrazione.
     * Registrare il momento del consenso, e non un semplice flag, rende tracciabile
     * quando è stato espresso, come richiesto in ottica di tutela dei dati personali.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $privacyConsentAt = null;

    /** Se true, l'email dell'utente è stata verificata */
    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    /** Se true, l'utente ha completato la selezione dei generi preferiti (onboarding) */
    #[ORM\Column(type: 'boolean')]
    private bool $onboardingCompleted = false;

    /** Se true, l'utente ha accesso alla dashboard di amministrazione */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isAdmin = false;

    /** Saldo Culture Points: valuta interna usata per fare offerte di prestito */
    #[ORM\Column(options: ['default' => 0])]
    private int $culturePoints = 0;

    /** Latitudine GPS del luogo di residenza (usata per la ricerca geolocalizzata) */
    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    /** Longitudine GPS del luogo di residenza */
    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    /** Generi letterari preferiti, scelti durante l'onboarding */
    /** @var Collection<int, Genre> */
    #[ORM\ManyToMany(targetEntity: Genre::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_preferred_genre')]
    private Collection $preferredGenres;

    /** Tutte le interazioni dell'utente con i libri (like, letto, possiedo) */
    /** @var Collection<int, UserBook> */
    #[ORM\OneToMany(targetEntity: UserBook::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userBooks;

    /** Utenti che questo utente segue */
    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'followers')]
    #[ORM\JoinTable(name: 'user_following')]
    private Collection $following;

    /** Utenti che seguono questo utente */
    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'following')]
    private Collection $followers;

    public function __construct()
    {
        $this->createdAt      = new \DateTimeImmutable();
        $this->preferredGenres = new ArrayCollection();
        $this->userBooks      = new ArrayCollection();
        $this->following      = new ArrayCollection();
        $this->followers      = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    /** Identificatore univoco usato da Symfony per l'autenticazione (l'email) */
    public function getUserIdentifier(): string { return (string) $this->email; }

    /**
     * Restituisce i ruoli dell'utente.
     * ROLE_USER è sempre garantito; ROLE_ADMIN aggiunto se isAdmin = true.
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        if ($this->isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }
        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    /** Metodo richiesto dall'interfaccia Symfony; non usato perché la password è già hashata */
    public function eraseCredentials(): void {}

    /** Restituisce il nome visualizzato; se non impostato, usa lo username */
    public function getDisplayName(): ?string { return $this->displayName ?? $this->username; }
    public function setDisplayName(?string $displayName): static { $this->displayName = $displayName; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): static { $this->bio = $bio; return $this; }

    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function setAvatarUrl(?string $avatarUrl): static { $this->avatarUrl = $avatarUrl; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getPrivacyConsentAt(): ?\DateTimeImmutable { return $this->privacyConsentAt; }
    public function setPrivacyConsentAt(?\DateTimeImmutable $privacyConsentAt): static { $this->privacyConsentAt = $privacyConsentAt; return $this; }

    /** True se l'utente ha prestato il consenso al trattamento dei dati personali */
    public function hasGivenPrivacyConsent(): bool { return $this->privacyConsentAt !== null; }

    /** Aggiorna automaticamente il timestamp prima di ogni UPDATE su database */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    public function isOnboardingCompleted(): bool { return $this->onboardingCompleted; }
    public function setOnboardingCompleted(bool $onboardingCompleted): static { $this->onboardingCompleted = $onboardingCompleted; return $this; }

    public function isAdmin(): bool { return $this->isAdmin; }
    public function setIsAdmin(bool $isAdmin): static { $this->isAdmin = $isAdmin; return $this; }

    // ──────────────────────────────────────────────────────────────────────
    // Generi preferiti
    // ──────────────────────────────────────────────────────────────────────

    /** @return Collection<int, Genre> */
    public function getPreferredGenres(): Collection { return $this->preferredGenres; }

    public function addPreferredGenre(Genre $genre): static
    {
        if (!$this->preferredGenres->contains($genre)) {
            $this->preferredGenres->add($genre);
        }
        return $this;
    }

    public function removePreferredGenre(Genre $genre): static
    {
        $this->preferredGenres->removeElement($genre);
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Interazioni con i libri
    // ──────────────────────────────────────────────────────────────────────

    /** @return Collection<int, UserBook> */
    public function getUserBooks(): Collection { return $this->userBooks; }

    /** Filtra le interazioni e restituisce solo i libri segnati come letti */
    public function getReadBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->hasRead());
    }

    /** Filtra le interazioni e restituisce solo i libri posseduti fisicamente */
    public function getOwnedBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->ownsPhysically());
    }

    /** Filtra le interazioni e restituisce solo i libri a cui è stato messo "mi piace" */
    public function getLikedBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->isLiked());
    }

    // ──────────────────────────────────────────────────────────────────────
    // Sistema social: following / followers
    // ──────────────────────────────────────────────────────────────────────

    /** @return Collection<int, User> */
    public function getFollowing(): Collection { return $this->following; }

    /** Aggiunge un utente alla lista dei seguiti (impedisce auto-follow) */
    public function follow(User $user): static
    {
        if (!$this->following->contains($user) && $user !== $this) {
            $this->following->add($user);
        }
        return $this;
    }

    /** Rimuove un utente dalla lista dei seguiti */
    public function unfollow(User $user): static
    {
        $this->following->removeElement($user);
        return $this;
    }

    /** Verifica se questo utente segue già un altro utente */
    public function isFollowing(User $user): bool { return $this->following->contains($user); }

    /** @return Collection<int, User> */
    public function getFollowers(): Collection { return $this->followers; }

    public function getFollowersCount(): int { return $this->followers->count(); }
    public function getFollowingCount(): int { return $this->following->count(); }

    // ──────────────────────────────────────────────────────────────────────
    // Culture Points
    // ──────────────────────────────────────────────────────────────────────

    /** Restituisce il saldo attuale di Culture Points */
    public function getCulturePoints(): int { return $this->culturePoints; }

    /** Aggiunge (o sottrae, se negativo) punti al saldo dell'utente */
    public function addCulturePoints(int $points): static
    {
        $this->culturePoints += $points;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Posizione geografica
    // ──────────────────────────────────────────────────────────────────────

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): static { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): static { $this->longitude = $longitude; return $this; }

    /** Restituisce true solo se entrambe le coordinate GPS sono impostate */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function __toString(): string { return $this->username ?? ''; }
}
