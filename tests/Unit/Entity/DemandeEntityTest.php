<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Demande;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Demande.
 *
 * On vérifie les constantes de statut, le statut par défaut,
 * et la validation interne de setStatus().
 */
class DemandeEntityTest extends TestCase
{
    // ------------------------------------------------------------------
    // Constantes de statut
    // ------------------------------------------------------------------

    public function testStatusConstantsHaveCorrectValues(): void
    {
        $this->assertSame('ouverte',   Demande::STATUS_OUVERTE);
        $this->assertSame('en_cours',  Demande::STATUS_EN_COURS);
        $this->assertSame('validée',   Demande::STATUS_VALIDEE);
        $this->assertSame('fermée',    Demande::STATUS_FERMEE);
    }

    // ------------------------------------------------------------------
    // Statut par défaut
    // ------------------------------------------------------------------

    public function testNewDemandeHasStatusOuverte(): void
    {
        $demande = new Demande();

        $this->assertSame(Demande::STATUS_OUVERTE, $demande->getStatus());
    }

    // ------------------------------------------------------------------
    // Transitions valides
    // ------------------------------------------------------------------

    public function testSetStatusWithValidValues(): void
    {
        $demande = new Demande();

        foreach ([
            Demande::STATUS_OUVERTE,
            Demande::STATUS_EN_COURS,
            Demande::STATUS_VALIDEE,
            Demande::STATUS_FERMEE,
        ] as $status) {
            $demande->setStatus($status);
            $this->assertSame($status, $demande->getStatus());
        }
    }

    // ------------------------------------------------------------------
    // Statut invalide → exception
    // ------------------------------------------------------------------

    public function testSetStatusWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Statut invalide/');

        (new Demande())->setStatus('inexistant');
    }

    public function testSetStatusWithEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Demande())->setStatus('');
    }

    // ------------------------------------------------------------------
    // ID auto-généré
    // ------------------------------------------------------------------

    public function testNewDemandeHasNonEmptyId(): void
    {
        $demande = new Demande();

        $this->assertNotEmpty($demande->getId());
    }

    public function testTwoDemandeshaveDifferentIds(): void
    {
        $a = new Demande();
        $b = new Demande();

        $this->assertNotSame($a->getId(), $b->getId());
    }

    // ------------------------------------------------------------------
    // Setters de base
    // ------------------------------------------------------------------

    public function testSettersReturnStatic(): void
    {
        $demande = new Demande();

        $this->assertSame($demande, $demande->setTitle('Mon titre'));
        $this->assertSame($demande, $demande->setDescription('Description'));
        $this->assertSame($demande, $demande->setCategory('dev'));
        $this->assertSame($demande, $demande->setBudget(1000.0));
    }

    public function testGettersReturnSetValues(): void
    {
        $demande = (new Demande())
            ->setTitle('Besoin développeur')
            ->setDescription('Créer une API')
            ->setCategory('développement')
            ->setBudget(2000.50);

        $this->assertSame('Besoin développeur', $demande->getTitle());
        $this->assertSame('Créer une API',       $demande->getDescription());
        $this->assertSame('développement',       $demande->getCategory());
        $this->assertSame(2000.50,               $demande->getBudget());
    }
}
