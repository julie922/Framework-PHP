<?php

namespace App\Tests\Integration;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Role;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Classe de base pour tous les tests d'intégration.
 *
 * Chaque test reçoit une base SQLite in-memory entièrement vierge
 * (drop + create schema) avec les trois rôles pré-chargés.
 */
abstract class AbstractIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        // Démarre le kernel en environnement "test"
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Recrée le schéma depuis zéro à chaque test → isolation totale
        $schemaTool = new SchemaTool($this->em);
        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Données minimales : les trois rôles (requis par AuthService)
        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        // Libère la connexion SQLite in-memory
        $this->em->close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Seeders
    // ------------------------------------------------------------------

    protected function seedRoles(): void
    {
        foreach (['ROLE_USER', 'ROLE_PRESTATAIRE', 'ROLE_ADMIN'] as $name) {
            $role = new Role();
            $role->setName($name)->setPermissions([]);
            $this->em->persist($role);
        }
        $this->em->flush();
    }

    // ------------------------------------------------------------------
    // Factories de test
    // ------------------------------------------------------------------

    /**
     * Crée et persiste un utilisateur avec le rôle donné.
     */
    protected function createUser(
        string $email,
        string $roleName,
        string $password = 'Password123!',
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        /** @var RoleRepository $roleRepo */
        $roleRepo = self::getContainer()->get(RoleRepository::class);

        $user = new User();
        $user->setEmail($email)
             ->setFirstName('Test')
             ->setLastName('User')
             ->setPassword($hasher->hashPassword($user, $password));

        $role = $roleRepo->findByName($roleName);
        $this->assertNotNull($role, "Le rôle $roleName doit exister en DB.");
        $user->addUserRole($role);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Crée et persiste un service.
     */
    protected function createService(User $prestataire, array $overrides = []): Service
    {
        $service = new Service();
        $service->setPrestataire($prestataire)
                ->setTitle($overrides['title'] ?? 'Service Test')
                ->setDescription($overrides['description'] ?? 'Description test')
                ->setCategory($overrides['category'] ?? 'développement')
                ->setPrice((float) ($overrides['price'] ?? 1000.0));

        if (isset($overrides['status'])) {
            $service->setStatus($overrides['status']);
        }

        $this->em->persist($service);
        $this->em->flush();

        return $service;
    }

    /**
     * Crée et persiste une demande.
     */
    protected function createDemande(User $demandeur, array $overrides = []): Demande
    {
        $demande = new Demande();
        $demande->setDemandeur($demandeur)
                ->setTitle($overrides['title'] ?? 'Demande Test')
                ->setDescription($overrides['description'] ?? 'Description test')
                ->setCategory($overrides['category'] ?? 'développement')
                ->setBudget((float) ($overrides['budget'] ?? 1500.0));

        if (isset($overrides['status'])) {
            $demande->setStatus($overrides['status']);
        }

        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    /**
     * Crée et persiste une proposition.
     */
    protected function createProposition(
        User $prestataire,
        Demande $demande,
        array $overrides = [],
    ): Proposition {
        $proposition = new Proposition();
        $proposition->setPrestataire($prestataire)
                    ->setDemande($demande)
                    ->setPrice((float) ($overrides['price'] ?? 900.0))
                    ->setMessage($overrides['message'] ?? 'Je propose mes services pour ce projet.');

        if (isset($overrides['status'])) {
            $proposition->setStatus($overrides['status']);
        }

        $this->em->persist($proposition);
        $this->em->flush();

        return $proposition;
    }
}
