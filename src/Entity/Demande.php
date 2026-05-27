<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
#[ORM\Table(name: 'demande')]
#[ORM\HasLifecycleCallbacks]
class Demande
{
    use TimestampableTrait;

    public const STATUS_OUVERTE  = 'ouverte';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_VALIDEE  = 'validée';
    public const STATUS_FERMEE   = 'fermée';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 100)]
    private string $category;

    #[ORM\Column(type: 'float')]
    private float $budget;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'demandes')]
    #[ORM\JoinColumn(nullable: false)]
    private User $demandeur;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OUVERTE;

    #[ORM\OneToMany(targetEntity: Proposition::class, mappedBy: 'demande', cascade: ['remove'])]
    private Collection $propositions;

    public function __construct()
    {
        $this->id = Role::generateUuid();
        $this->propositions = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getBudget(): float
    {
        return $this->budget;
    }

    public function setBudget(float $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getDemandeur(): User
    {
        return $this->demandeur;
    }

    public function setDemandeur(User $demandeur): static
    {
        $this->demandeur = $demandeur;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $allowed = [self::STATUS_OUVERTE, self::STATUS_EN_COURS, self::STATUS_VALIDEE, self::STATUS_FERMEE];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Statut invalide: $status");
        }
        $this->status = $status;
        return $this;
    }

    public function getPropositions(): Collection
    {
        return $this->propositions;
    }
}
