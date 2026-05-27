<?php

namespace App\Tests\Unit\Security;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Service;
use App\Entity\User;
use App\Security\ResourceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Tests unitaires de ResourceVoter.
 *
 * On instancie le voter directement (pas de dépendances) et on mocke
 * TokenInterface pour lui injecter les utilisateurs de test.
 */
class ResourceVoterTest extends TestCase
{
    private ResourceVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ResourceVoter();
    }

    // ------------------------------------------------------------------
    // ROLE_ADMIN : accès total
    // ------------------------------------------------------------------

    public function testAdminCanEditAnyService(): void
    {
        $admin   = $this->makeAdmin();
        $owner   = $this->makeUser();
        $service = $this->makeService($owner);

        // L'admin n'est pas propriétaire, mais doit quand même avoir accès
        $result = $this->voter->vote($this->tokenFor($admin), $service, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAdminCanDeleteAnyDemande(): void
    {
        $admin   = $this->makeAdmin();
        $owner   = $this->makeUser();
        $demande = $this->makeDemande($owner);

        $result = $this->voter->vote($this->tokenFor($admin), $demande, [ResourceVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ------------------------------------------------------------------
    // Service
    // ------------------------------------------------------------------

    public function testOwnerCanEditOwnService(): void
    {
        $owner   = $this->makeUser();
        $service = $this->makeService($owner);

        $result = $this->voter->vote($this->tokenFor($owner), $service, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testNonOwnerCannotEditService(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $service = $this->makeService($owner);

        $result = $this->voter->vote($this->tokenFor($other), $service, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testNonOwnerCannotDeleteService(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $service = $this->makeService($owner);

        $result = $this->voter->vote($this->tokenFor($other), $service, [ResourceVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ------------------------------------------------------------------
    // Demande
    // ------------------------------------------------------------------

    public function testOwnerCanEditOwnDemande(): void
    {
        $owner   = $this->makeUser();
        $demande = $this->makeDemande($owner);

        $result = $this->voter->vote($this->tokenFor($owner), $demande, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testNonOwnerCannotEditDemande(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $demande = $this->makeDemande($owner);

        $result = $this->voter->vote($this->tokenFor($other), $demande, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ------------------------------------------------------------------
    // Proposition
    // ------------------------------------------------------------------

    public function testDemandeurCanEditProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        // Le demandeur peut accepter/refuser la proposition
        $result = $this->voter->vote($this->tokenFor($demandeur), $proposition, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPrestataireCanEditOwnProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        // Le prestataire peut annuler sa propre proposition
        $result = $this->voter->vote($this->tokenFor($prestataire), $proposition, [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPrestataireCanDeleteOwnProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        $result = $this->voter->vote($this->tokenFor($prestataire), $proposition, [ResourceVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testRandomUserCannotDeleteProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $other       = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        $result = $this->voter->vote($this->tokenFor($other), $proposition, [ResourceVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDemandeurCanViewProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        $result = $this->voter->vote($this->tokenFor($demandeur), $proposition, [ResourceVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testRandomUserCannotViewProposition(): void
    {
        $demandeur   = $this->makeUser();
        $prestataire = $this->makeUser();
        $other       = $this->makeUser();
        $demande     = $this->makeDemande($demandeur);
        $proposition = $this->makeProposition($prestataire, $demande);

        $result = $this->voter->vote($this->tokenFor($other), $proposition, [ResourceVoter::VIEW]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ------------------------------------------------------------------
    // Abstention : sujet non supporté
    // ------------------------------------------------------------------

    public function testVoterAbstainsForUnsupportedSubject(): void
    {
        $user   = $this->makeUser();
        $result = $this->voter->vote($this->tokenFor($user), new \stdClass(), [ResourceVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('user_') . '@test.com')
             ->setFirstName('Test')
             ->setLastName('User')
             ->setPassword('x');

        return $user;
    }

    private function makeAdmin(): User
    {
        $role = (new \App\Entity\Role())->setName('ROLE_ADMIN');
        $user = $this->makeUser();
        $user->addUserRole($role);

        return $user;
    }

    private function makeService(User $prestataire): Service
    {
        $service = new Service();
        $service->setPrestataire($prestataire)
                ->setTitle('Test Service')
                ->setDescription('desc')
                ->setCategory('dev')
                ->setPrice(100.0);

        return $service;
    }

    private function makeDemande(User $demandeur): Demande
    {
        $demande = new Demande();
        $demande->setDemandeur($demandeur)
                ->setTitle('Test Demande')
                ->setDescription('desc')
                ->setCategory('dev')
                ->setBudget(500.0);

        return $demande;
    }

    private function makeProposition(User $prestataire, Demande $demande): Proposition
    {
        $proposition = new Proposition();
        $proposition->setPrestataire($prestataire)
                    ->setDemande($demande)
                    ->setPrice(400.0)
                    ->setMessage('Proposition de test suffisamment longue.');

        return $proposition;
    }

    private function tokenFor(User $user): TokenInterface
    {
        // createStub() : on contrôle la valeur de retour sans vérifier le nombre d'appels
        // → PHPUnit 13 n'émet pas de notice "no expectations configured"
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
