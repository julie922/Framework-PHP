<?php

namespace App\Tests\Integration\Service;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Repository\PropositionRepository;
use App\Service\PropositionService;
use App\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Tests d'intégration pour PropositionService.
 *
 * Couvre la création de propositions, l'acceptation avec cascades,
 * le refus, l'annulation et les règles métier associées.
 */
class PropositionServiceTest extends AbstractIntegrationTestCase
{
    private PropositionService  $propositionService;
    private PropositionRepository $propositionRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->propositionService = self::getContainer()->get(PropositionService::class);
        $this->propositionRepo    = self::getContainer()->get(PropositionRepository::class);
    }

    // ------------------------------------------------------------------
    // create()
    // ------------------------------------------------------------------

    public function testCreatePropositionWithValidData(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);

        $proposition = $this->propositionService->create($prestataire, $demande, [
            'price'   => 1800.0,
            'message' => 'Je peux livrer ce projet en 3 semaines avec tests.',
        ]);

        $this->assertNotEmpty($proposition->getId());
        $this->assertSame(1800.0,                          $proposition->getPrice());
        $this->assertSame(Proposition::STATUS_EN_ATTENTE,  $proposition->getStatus());
        $this->assertSame($prestataire->getId(),           $proposition->getPrestataire()->getId());
        $this->assertSame($demande->getId(),               $proposition->getDemande()->getId());
    }

    public function testCreatePropositionOnClosedDemandeThrowsLogicException(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur, ['status' => Demande::STATUS_FERMEE]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/non ouverte/');

        $this->propositionService->create($prestataire, $demande, [
            'price'   => 500.0,
            'message' => 'Tentative sur demande fermée.',
        ]);
    }

    public function testCreatePropositionOnEnCoursDemandeThrowsLogicException(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur, ['status' => Demande::STATUS_EN_COURS]);

        $this->expectException(\LogicException::class);

        $this->propositionService->create($prestataire, $demande, [
            'price'   => 500.0,
            'message' => 'Tentative sur demande en cours.',
        ]);
    }

    // ------------------------------------------------------------------
    // updateStatus() — accepter
    // ------------------------------------------------------------------

    public function testAcceptPropositionChangesStatusToAcceptee(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $result = $this->propositionService->updateStatus($proposition, Proposition::STATUS_ACCEPTEE, $demandeur);

        $this->assertSame(Proposition::STATUS_ACCEPTEE, $result->getStatus());
    }

    public function testAcceptPropositionSetsDemandeToEnCours(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $this->propositionService->updateStatus($proposition, Proposition::STATUS_ACCEPTEE, $demandeur);

        // On recharge la demande depuis la DB pour vérifier la cascade
        $this->em->refresh($demande);
        $this->assertSame(Demande::STATUS_EN_COURS, $demande->getStatus());
    }

    public function testAcceptPropositionRefusesAllOtherPendingPropositions(): void
    {
        $demandeur    = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire1 = $this->createUser('bob@test.com',   'ROLE_PRESTATAIRE');
        $prestataire2 = $this->createUser('carol@test.com', 'ROLE_PRESTATAIRE');
        $prestataire3 = $this->createUser('dave@test.com',  'ROLE_PRESTATAIRE');
        $demande      = $this->createDemande($demandeur);

        $prop1 = $this->createProposition($prestataire1, $demande);
        $prop2 = $this->createProposition($prestataire2, $demande);
        $prop3 = $this->createProposition($prestataire3, $demande);

        // On accepte prop1
        $this->propositionService->updateStatus($prop1, Proposition::STATUS_ACCEPTEE, $demandeur);

        // Les deux autres doivent être "refusée"
        $this->em->refresh($prop2);
        $this->em->refresh($prop3);

        $this->assertSame(Proposition::STATUS_REFUSEE, $prop2->getStatus(),
            'prop2 doit être refusée après acceptation de prop1');
        $this->assertSame(Proposition::STATUS_REFUSEE, $prop3->getStatus(),
            'prop3 doit être refusée après acceptation de prop1');
    }

    public function testOnlyDemandeurCanAcceptPropositionThrowsLogicException(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/demandeur/');

        // Le prestataire tente d'accepter sa propre proposition → interdit
        $this->propositionService->updateStatus($proposition, Proposition::STATUS_ACCEPTEE, $prestataire);
    }

    public function testCannotAcceptAlreadyAcceptedProposition(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande, ['status' => Proposition::STATUS_ACCEPTEE]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/en attente/');

        $this->propositionService->updateStatus($proposition, Proposition::STATUS_ACCEPTEE, $demandeur);
    }

    // ------------------------------------------------------------------
    // updateStatus() — refuser
    // ------------------------------------------------------------------

    public function testRefusePropositionChangesStatusToRefusee(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $result = $this->propositionService->updateStatus($proposition, Proposition::STATUS_REFUSEE, $demandeur);

        $this->assertSame(Proposition::STATUS_REFUSEE, $result->getStatus());
    }

    public function testOnlyDemandeurCanRefuseProposition(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/demandeur/');

        $this->propositionService->updateStatus($proposition, Proposition::STATUS_REFUSEE, $prestataire);
    }

    // ------------------------------------------------------------------
    // updateStatus() — annuler
    // ------------------------------------------------------------------

    public function testPrestataireCanCancelOwnProposition(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $result = $this->propositionService->updateStatus($proposition, Proposition::STATUS_ANNULEE, $prestataire);

        $this->assertSame(Proposition::STATUS_ANNULEE, $result->getStatus());
    }

    public function testDemandeurCannotCancelProposition(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/prestataire/');

        $this->propositionService->updateStatus($proposition, Proposition::STATUS_ANNULEE, $demandeur);
    }

    // ------------------------------------------------------------------
    // delete()
    // ------------------------------------------------------------------

    public function testDeletePropositionRemovesItFromDatabase(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);
        $id          = $proposition->getId();

        $this->propositionService->delete($proposition);
        $this->em->clear();

        $found = $this->em->getRepository(Proposition::class)->find($id);
        $this->assertNull($found, 'La proposition supprimée ne doit plus exister en base');
    }

    // ------------------------------------------------------------------
    // serialize()
    // ------------------------------------------------------------------

    public function testSerializeReturnsExpectedKeys(): void
    {
        $demandeur   = $this->createUser('alice@test.com', 'ROLE_USER');
        $prestataire = $this->createUser('bob@test.com', 'ROLE_PRESTATAIRE');
        $demande     = $this->createDemande($demandeur);
        $proposition = $this->createProposition($prestataire, $demande);

        $data = $this->propositionService->serialize($proposition);

        foreach (['id', 'price', 'message', 'status', 'demande', 'prestataire', 'createdAt', 'updatedAt'] as $key) {
            $this->assertArrayHasKey($key, $data, "La clé '$key' doit être présente dans la sérialisation");
        }
    }
}
