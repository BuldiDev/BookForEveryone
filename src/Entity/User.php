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

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    /** Se true, l'utente ha completato la selezione generi iniziale */
    #[ORM\Column(type: 'boolean')]
    private bool $onboardingCompleted = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isAdmin = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $culturePoints = 0;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    /** @var Collection<int, Genre> Generi preferiti scelti dall'utente */
    #[ORM\ManyToMany(targetEntity: Genre::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_preferred_genre')]
    private Collection $preferredGenres;

    /** @var Collection<int, UserBook> Interazioni con i libri */
    #[ORM\OneToMany(targetEntity: UserBook::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userBooks;

    /**
     * Users that this user follows.
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'followers')]
    #[ORM\JoinTable(name: 'user_following')]
    private Collection $following;

    /**
     * Users that follow this user.
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'following')]
    private Collection $followers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->preferredGenres = new ArrayCollection();
        $this->userBooks = new ArrayCollection();
        $this->following = new ArrayCollection();
        $this->followers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        if ($this->isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }
        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName ?? $this->username;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;
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

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isOnboardingCompleted(): bool
    {
        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $onboardingCompleted): static
    {
        $this->onboardingCompleted = $onboardingCompleted;
        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;
        return $this;
    }

    // --- Generi preferiti ---

    /** @return Collection<int, Genre> */
    public function getPreferredGenres(): Collection
    {
        return $this->preferredGenres;
    }

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

    // --- Interazioni libri ---

    /** @return Collection<int, UserBook> */
    public function getUserBooks(): Collection
    {
        return $this->userBooks;
    }

    /** Libri che l'utente ha letto */
    public function getReadBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->hasRead());
    }

    /** Libri che l'utente possiede fisicamente */
    public function getOwnedBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->ownsPhysically());
    }

    /** Libri che l'utente ha messo like */
    public function getLikedBooks(): Collection
    {
        return $this->userBooks->filter(fn(UserBook $ub) => $ub->isLiked());
    }

    // --- Following / Followers ---

    /** @return Collection<int, User> */
    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function follow(User $user): static
    {
        if (!$this->following->contains($user) && $user !== $this) {
            $this->following->add($user);
        }
        return $this;
    }

    public function unfollow(User $user): static
    {
        $this->following->removeElement($user);
        return $this;
    }

    public function isFollowing(User $user): bool
    {
        return $this->following->contains($user);
    }

    /** @return Collection<int, User> */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function getFollowersCount(): int
    {
        return $this->followers->count();
    }

    public function getFollowingCount(): int
    {
        return $this->following->count();
    }

    // --- Punti cultura ---

    public function getCulturePoints(): int
    {
        return $this->culturePoints;
    }

    public function addCulturePoints(int $points): static
    {
        $this->culturePoints += $points;
        return $this;
    }

    // --- Posizione ---

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function __toString(): string
    {
        return $this->username ?? '';
    }
}
