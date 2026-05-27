<?php

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Repository\PropositionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropositionRepository::class)]
#[ORM\Table(name: 'proposition')]
#[ORM\HasLifecycleCallbacks]
class Proposition
{
    use TimestampableTrait;

    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_ACCEPTEE   = 'acceptée';
    public const STATUS_REFUSEE    = 'refusée';
    public const STATUS_ANNULEE    = 'annulée';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'propositions')]
    #[ORM\JoinColumn(nullable: false)]
    private Demande $demande;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'propositions')]
    #[ORM\JoinColumn(nullable: false)]
    private User $prestataire;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Service $service = null;

    #[ORM\Column(type: 'float')]
    private float $price;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_EN_ATTENTE;

    public function __construct()
    {
        $this->id = Role::generateUuid();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDemande(): Demande
    {
        return $this->demande;
    }

    public function setDemande(Demande $demande): static
    {
        $this->demande = $demande;
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $allowed = [self::STATUS_EN_ATTENTE, self::STATUS_ACCEPTEE, self::STATUS_REFUSEE, self::STATUS_ANNULEE];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Statut invalide: $status");
        }
        $this->status = $status;
        return $this;
    }
}
