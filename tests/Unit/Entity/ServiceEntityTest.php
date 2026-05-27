<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Service;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Service.
 *
 * Couvre les constantes de statut, le statut par défaut,
 * la validation de setStatus(), la génération d'UUID et les setters/getters.
 */
class ServiceEntityTest extends TestCase
{
    // ------------------------------------------------------------------
    // Constantes de statut
    // ------------------------------------------------------------------

    public function testStatusConstantsHaveCorrectValues(): void
    {
        $this->assertSame('active',   Service::STATUS_ACTIVE);
        $this->assertSame('inactive', Service::STATUS_INACTIVE);
        $this->assertSame('archived', Service::STATUS_ARCHIVED);
    }

    // ------------------------------------------------------------------
    // Statut par défaut
    // ------------------------------------------------------------------

    public function testNewServiceHasStatusActive(): void
    {
        $service = new Service();

        $this->assertSame(Service::STATUS_ACTIVE, $service->getStatus());
    }

    // ------------------------------------------------------------------
    // Transitions valides
    // ------------------------------------------------------------------

    public function testSetStatusWithValidValues(): void
    {
        $service = new Service();

        foreach ([
            Service::STATUS_ACTIVE,
            Service::STATUS_INACTIVE,
            Service::STATUS_ARCHIVED,
        ] as $status) {
            $service->setStatus($status);
            $this->assertSame($status, $service->getStatus());
        }
    }

    // ------------------------------------------------------------------
    // Statut invalide → exception
    // ------------------------------------------------------------------

    public function testSetStatusWithInvalidValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Statut invalide/');

        (new Service())->setStatus('brouillon');
    }

    public function testSetStatusWithEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Service())->setStatus('');
    }

    // ------------------------------------------------------------------
    // ID auto-généré (UUID v4)
    // ------------------------------------------------------------------

    public function testNewServiceHasNonEmptyId(): void
    {
        $this->assertNotEmpty((new Service())->getId());
    }

    public function testNewServiceIdMatchesUuidV4Format(): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, (new Service())->getId());
    }

    public function testTwoServicesHaveDifferentIds(): void
    {
        $this->assertNotSame(
            (new Service())->getId(),
            (new Service())->getId()
        );
    }

    // ------------------------------------------------------------------
    // Setters retournent $this (fluent interface)
    // ------------------------------------------------------------------

    public function testSettersReturnStatic(): void
    {
        $service = new Service();

        $this->assertSame($service, $service->setTitle('API Symfony'));
        $this->assertSame($service, $service->setDescription('Développement API REST'));
        $this->assertSame($service, $service->setCategory('développement'));
        $this->assertSame($service, $service->setPrice(1500.0));
        $this->assertSame($service, $service->setStatus(Service::STATUS_ACTIVE));
    }

    // ------------------------------------------------------------------
    // Getters retournent les valeurs définies
    // ------------------------------------------------------------------

    public function testGettersReturnSetValues(): void
    {
        $service = (new Service())
            ->setTitle('Refonte site web')
            ->setDescription('Intégration HTML/CSS responsive')
            ->setCategory('design')
            ->setPrice(800.50);

        $this->assertSame('Refonte site web',                $service->getTitle());
        $this->assertSame('Intégration HTML/CSS responsive', $service->getDescription());
        $this->assertSame('design',                          $service->getCategory());
        $this->assertSame(800.50,                            $service->getPrice());
    }

    // ------------------------------------------------------------------
    // Prix — types
    // ------------------------------------------------------------------

    public function testPriceAcceptsInteger(): void
    {
        $service = (new Service())->setPrice(1000);

        // Doctrine stocke un float ; PHP caste automatiquement l'int
        $this->assertSame(1000.0, $service->getPrice());
    }

    public function testPriceAcceptsDecimal(): void
    {
        $service = (new Service())->setPrice(49.99);

        $this->assertSame(49.99, $service->getPrice());
    }
}
