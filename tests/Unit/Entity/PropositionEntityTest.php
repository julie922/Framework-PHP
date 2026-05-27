<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Proposition;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Proposition.
 */
class PropositionEntityTest extends TestCase
{
    // ------------------------------------------------------------------
    // Constantes de statut
    // ------------------------------------------------------------------

    public function testStatusConstantsHaveCorrectValues(): void
    {
        $this->assertSame('en_attente', Proposition::STATUS_EN_ATTENTE);
        $this->assertSame('acceptée',   Proposition::STATUS_ACCEPTEE);
        $this->assertSame('refusée',    Proposition::STATUS_REFUSEE);
        $this->assertSame('annulée',    Proposition::STATUS_ANNULEE);
    }

    // ------------------------------------------------------------------
    // Statut par défaut
    // ------------------------------------------------------------------

    public function testNewPropositionHasStatusEnAttente(): void
    {
        $proposition = new Proposition();

        $this->assertSame(Proposition::STATUS_EN_ATTENTE, $proposition->getStatus());
    }

    // ------------------------------------------------------------------
    // Transitions valides
    // ------------------------------------------------------------------

    public function testSetStatusWithValidValues(): void
    {
        $proposition = new Proposition();

        foreach ([
            Proposition::STATUS_EN_ATTENTE,
            Proposition::STATUS_ACCEPTEE,
            Proposition::STATUS_REFUSEE,
            Proposition::STATUS_ANNULEE,
        ] as $status) {
            $proposition->setStatus($status);
            $this->assertSame($status, $proposition->getStatus());
        }
    }

    // ------------------------------------------------------------------
    // Statut invalide → exception
    // ------------------------------------------------------------------

    public function testSetStatusWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Statut invalide/');

        (new Proposition())->setStatus('brouillon');
    }

    public function testSetStatusWithEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Proposition())->setStatus('');
    }

    // ------------------------------------------------------------------
    // ID auto-généré
    // ------------------------------------------------------------------

    public function testNewPropositionHasNonEmptyId(): void
    {
        $this->assertNotEmpty((new Proposition())->getId());
    }

    public function testTwoPropositionsHaveDifferentIds(): void
    {
        $this->assertNotSame(
            (new Proposition())->getId(),
            (new Proposition())->getId()
        );
    }

    // ------------------------------------------------------------------
    // Setters / Getters
    // ------------------------------------------------------------------

    public function testSettersReturnStatic(): void
    {
        $prop = new Proposition();

        $this->assertSame($prop, $prop->setPrice(1500.0));
        $this->assertSame($prop, $prop->setMessage('Livraison en 2 semaines.'));
    }

    public function testGettersReturnSetValues(): void
    {
        $prop = (new Proposition())
            ->setPrice(1900.0)
            ->setMessage('Je peux livrer en 3 semaines.');

        $this->assertSame(1900.0,                     $prop->getPrice());
        $this->assertSame('Je peux livrer en 3 semaines.', $prop->getMessage());
    }

    public function testServiceIsNullByDefault(): void
    {
        $this->assertNull((new Proposition())->getService());
    }
}
