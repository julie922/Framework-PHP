<?php

namespace App\Tests\Integration\Service;

use App\Entity\Demande;
use App\Service\DemandeService;
use App\Tests\Integration\AbstractIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests d'intégration pour DemandeService.
 *
 * Couvre la création, la mise à jour de contenu et les transitions d'état.
 */
class DemandeServiceTest extends AbstractIntegrationTestCase
{
    private DemandeService $demandeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demandeService = self::getContainer()->get(DemandeService::class);
    }

    // ------------------------------------------------------------------
    // create()
    // ------------------------------------------------------------------

    public function testCreateDemandeWithValidData(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');

        $demande = $this->demandeService->create($demandeur, [
            'title'       => 'Besoin développeur PHP',
            'description' => 'Créer une API marketplace avec Symfony',
            'category'    => 'développement',
            'budget'      => 2000.0,
        ]);

        $this->assertNotEmpty($demande->getId());
        $this->assertSame('Besoin développeur PHP',              $demande->getTitle());
        $this->assertSame('Créer une API marketplace avec Symfony', $demande->getDescription());
        $this->assertSame('développement',                       $demande->getCategory());
        $this->assertSame(2000.0,                                $demande->getBudget());
        $this->assertSame(Demande::STATUS_OUVERTE,               $demande->getStatus());
        $this->assertSame($demandeur->getId(),                   $demande->getDemandeur()->getId());
    }

    public function testCreatedDemandeIsPersisted(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');

        $demande = $this->demandeService->create($demandeur, [
            'title'       => 'Test persistance',
            'description' => 'Vérifier que la demande est en base',
            'category'    => 'test',
            'budget'      => 100.0,
        ]);

        $this->em->clear(); // Vide le cache du repository

        $found = $this->em->getRepository(Demande::class)->find($demande->getId());
        $this->assertNotNull($found);
        $this->assertSame('Test persistance', $found->getTitle());
    }

    // ------------------------------------------------------------------
    // update() — modification du contenu
    // ------------------------------------------------------------------

    public function testUpdateDemandeContentWhenOuverte(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur, ['status' => Demande::STATUS_OUVERTE]);

        $updated = $this->demandeService->update($demande, [
            'title'  => 'Nouveau titre',
            'budget' => 3000.0,
        ]);

        $this->assertSame('Nouveau titre', $updated->getTitle());
        $this->assertSame(3000.0,          $updated->getBudget());
    }

    public function testUpdateContentOnClosedDemandeThrowsLogicException(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur, ['status' => Demande::STATUS_FERMEE]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/ouvertes/');

        $this->demandeService->update($demande, ['title' => 'Tentative de modif']);
    }

    // ------------------------------------------------------------------
    // update() — transitions d'état
    // ------------------------------------------------------------------

    #[DataProvider('validTransitionsProvider')]
    public function testValidStatusTransitions(string $from, string $to): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur, ['status' => $from]);

        $updated = $this->demandeService->update($demande, ['status' => $to]);

        $this->assertSame($to, $updated->getStatus());
    }

    public static function validTransitionsProvider(): array
    {
        return [
            'ouverte → fermée'         => [Demande::STATUS_OUVERTE,   Demande::STATUS_FERMEE],
            'en_cours → validée'       => [Demande::STATUS_EN_COURS,   Demande::STATUS_VALIDEE],
            'en_cours → ouverte'       => [Demande::STATUS_EN_COURS,   Demande::STATUS_OUVERTE],
            'validée → fermée'         => [Demande::STATUS_VALIDEE,    Demande::STATUS_FERMEE],
        ];
    }

    #[DataProvider('invalidTransitionsProvider')]
    public function testInvalidStatusTransitionsThrowLogicException(string $from, string $to): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur, ['status' => $from]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Transition interdite/');

        $this->demandeService->update($demande, ['status' => $to]);
    }

    public static function invalidTransitionsProvider(): array
    {
        return [
            'ouverte → en_cours directement' => [Demande::STATUS_OUVERTE, Demande::STATUS_EN_COURS],
            'ouverte → validée directement'   => [Demande::STATUS_OUVERTE, Demande::STATUS_VALIDEE],
            'fermée → ouverte'               => [Demande::STATUS_FERMEE,  Demande::STATUS_OUVERTE],
            'validée → en_cours'             => [Demande::STATUS_VALIDEE, Demande::STATUS_EN_COURS],
        ];
    }

    // ------------------------------------------------------------------
    // delete()
    // ------------------------------------------------------------------

    public function testDeleteDemandeRemovesItFromDatabase(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur);
        $id        = $demande->getId();

        $this->demandeService->delete($demande);
        $this->em->clear();

        $found = $this->em->getRepository(Demande::class)->find($id);
        $this->assertNull($found, 'La demande supprimée ne doit plus exister en base');
    }

    // ------------------------------------------------------------------
    // serialize()
    // ------------------------------------------------------------------

    public function testSerializeReturnsExpectedKeys(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur);

        $data = $this->demandeService->serialize($demande);

        $expectedKeys = ['id', 'title', 'description', 'category', 'budget', 'status', 'demandeur', 'createdAt', 'updatedAt'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "La clé '$key' doit être présente dans la sérialisation");
        }
    }

    public function testSerializeWithPropositionsIncludesPropositionsKey(): void
    {
        $demandeur = $this->createUser('alice@test.com', 'ROLE_USER');
        $demande   = $this->createDemande($demandeur);

        $data = $this->demandeService->serialize($demande, withPropositions: true);

        $this->assertArrayHasKey('propositions', $data);
        $this->assertIsArray($data['propositions']);
    }
}
