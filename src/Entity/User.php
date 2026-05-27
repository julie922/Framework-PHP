<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\ManyToMany(targetEntity: Role::class, fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $userRoles;

    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'prestataire', cascade: ['remove'])]
    private Collection $services;

    #[ORM\OneToMany(targetEntity: Demande::class, mappedBy: 'demandeur', cascade: ['remove'])]
    private Collection $demandes;

    #[ORM\OneToMany(targetEntity: Proposition::class, mappedBy: 'prestataire', cascade: ['remove'])]
    private Collection $propositions;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $refreshTokenExpiresAt = null;

    public function __construct()
    {
        $this->id = Role::generateUuid();
        $this->userRoles = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->propositions = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->userRoles->map(fn(Role $r) => $r->getName())->toArray();
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(Role $role): static
    {
        if (!$this->userRoles->contains($role)) {
            $this->userRoles->add($role);
        }
        return $this;
    }

    public function removeUserRole(Role $role): static
    {
        $this->userRoles->removeElement($role);
        return $this;
    }

    public function hasRole(string $roleName): bool
    {
        return in_array($roleName, $this->getRoles(), true);
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getRefreshTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->refreshTokenExpiresAt;
    }

    public function setRefreshTokenExpiresAt(?\DateTimeImmutable $refreshTokenExpiresAt): static
    {
        $this->refreshTokenExpiresAt = $refreshTokenExpiresAt;
        return $this;
    }

    public function getServices(): Collection
    {
        return $this->services;
    }

    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function getPropositions(): Collection
    {
        return $this->propositions;
    }

    public function eraseCredentials(): void {}
}
