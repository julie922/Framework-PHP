<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'role')]
class Role
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $name;

    // Réservé pour des permissions fines plus tard (ex: ["can_publish", "can_delete"])
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    public function __construct()
    {
        $this->id = self::generateUuid();
    }

    // Méthode statique car utilisée par toutes les entités via leur propre constructeur.
    public static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }
}
