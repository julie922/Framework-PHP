<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\ServiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'service')]
#[ORM\HasLifecycleCallbacks]
class Service
{
    use TimestampableTrait;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

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
    private float $price;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false)]
    private User $prestataire;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    public function __construct()
    {
        $this->id = Role::generateUuid();
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

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getPrestataire(): User
    {
        return $this->prestataire;
    }

    public function setPrestataire(User $prestataire): static
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \InvalidArgumentException("Statut invalide: $status");
        }
        $this->status = $status;
        return $this;
    }
}
