<?php

namespace App\DataFixtures;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Role;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Rôles
        $roleUser         = $this->createRole($manager, 'ROLE_USER',         ['read_services', 'create_demandes']);
        $rolePrestataire  = $this->createRole($manager, 'ROLE_PRESTATAIRE',  ['read_services', 'create_services', 'create_propositions']);
        $roleAdmin        = $this->createRole($manager, 'ROLE_ADMIN',        ['*']);

        $manager->flush();

        // Utilisateurs
        $client    = $this->createUser($manager, 'client@test.com',   'Password123!', 'Alice', 'Demandeur',  [$roleUser]);
        $provider  = $this->createUser($manager, 'provider@test.com', 'Password123!', 'Bob',   'Prestataire', [$rolePrestataire]);
        $admin     = $this->createUser($manager, 'admin@test.com',    'Password123!', 'Admin', 'System',     [$roleAdmin]);

        $manager->flush();

        // Services
        $service1 = $this->createService($manager, $provider, 'Développement Symfony',   'API REST Symfony professionnelle', 'développement', 1500);
        $service2 = $this->createService($manager, $provider, 'Design UI/UX',            'Maquettes Figma complètes',        'design',        800);
        $service3 = $this->createService($manager, $provider, 'Intégration React',        'Développement frontend React/TS',  'développement', 1200);
        $service4 = $this->createService($manager, $provider, 'SEO & Référencement',      'Audit et optimisation SEO',        'marketing',     500);
        $service5 = $this->createService($manager, $provider, 'Support DevOps',           'CI/CD Docker Kubernetes',          'infrastructure', 2000, Service::STATUS_INACTIVE);

        $manager->flush();

        // Demandes
        $demande1 = $this->createDemande($manager, $client, 'Besoin développeur Symfony', 'Créer une API marketplace', 'développement', 2000);
        $demande2 = $this->createDemande($manager, $client, 'Logo et charte graphique',   'Créer une identité visuelle', 'design', 600);
        $demande3 = $this->createDemande($manager, $client, 'Refonte site web',           'Migrer vers React + API', 'développement', 3000, Demande::STATUS_EN_COURS);

        $manager->flush();

        // Propositions
        $prop1 = $this->createProposition($manager, $provider, $demande1, $service1, 1900, 'Je peux livrer en 3 semaines avec tests inclus.');
        $prop2 = $this->createProposition($manager, $provider, $demande2, $service2, 550,  'Design complet en 1 semaine.');
        $prop3 = $this->createProposition($manager, $provider, $demande3, $service3, 2800, 'Migration complète frontend.', Proposition::STATUS_ACCEPTEE);

        $manager->flush();
    }

    private function createRole(ObjectManager $manager, string $name, array $permissions): Role
    {
        $role = new Role();
        $role->setName($name)->setPermissions($permissions);
        $manager->persist($role);
        return $role;
    }

    private function createUser(ObjectManager $manager, string $email, string $plain, string $first, string $last, array $roles): User
    {
        $user = new User();
        $user->setEmail($email)
             ->setFirstName($first)
             ->setLastName($last)
             ->setPassword($this->passwordHasher->hashPassword($user, $plain));

        foreach ($roles as $role) {
            $user->addUserRole($role);
        }

        $manager->persist($user);
        return $user;
    }

    private function createService(
        ObjectManager $manager,
        User $prestataire,
        string $title,
        string $description,
        string $category,
        float $price,
        string $status = Service::STATUS_ACTIVE,
    ): Service {
        $service = new Service();
        $service->setPrestataire($prestataire)
                ->setTitle($title)
                ->setDescription($description)
                ->setCategory($category)
                ->setPrice($price)
                ->setStatus($status);

        $manager->persist($service);
        return $service;
    }

    private function createDemande(
        ObjectManager $manager,
        User $demandeur,
        string $title,
        string $description,
        string $category,
        float $budget,
        string $status = Demande::STATUS_OUVERTE,
    ): Demande {
        $demande = new Demande();
        $demande->setDemandeur($demandeur)
                ->setTitle($title)
                ->setDescription($description)
                ->setCategory($category)
                ->setBudget($budget)
                ->setStatus($status);

        $manager->persist($demande);
        return $demande;
    }

    private function createProposition(
        ObjectManager $manager,
        User $prestataire,
        Demande $demande,
        ?Service $service,
        float $price,
        string $message,
        string $status = Proposition::STATUS_EN_ATTENTE,
    ): Proposition {
        $proposition = new Proposition();
        $proposition->setPrestataire($prestataire)
                    ->setDemande($demande)
                    ->setService($service)
                    ->setPrice($price)
                    ->setMessage($message)
                    ->setStatus($status);

        $manager->persist($proposition);
        return $proposition;
    }
}
