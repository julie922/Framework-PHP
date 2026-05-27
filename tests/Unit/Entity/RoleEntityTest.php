<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Role;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Role.
 *
 * Vérifie principalement la génération des UUID v4.
 */
class RoleEntityTest extends TestCase
{
    // ------------------------------------------------------------------
    // UUID
    // ------------------------------------------------------------------

    public function testGenerateUuidMatchesUuidV4Format(): void
    {
        $uuid   = Role::generateUuid();
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, $uuid,
            'L\'UUID généré doit respecter le format UUID v4');
    }

    public function testGenerateUuidReturnsUniqueValues(): void
    {
        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = Role::generateUuid();
        }

        // array_unique doit retourner autant d'éléments → pas de doublon
        $this->assertCount(50, array_unique($ids),
            '50 UUID générés consécutivement doivent tous être uniques');
    }

    public function testNewRoleHasValidId(): void
    {
        $role    = new Role();
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, $role->getId());
    }

    // ------------------------------------------------------------------
    // Setters / Getters
    // ------------------------------------------------------------------

    public function testSetNameReturnsStatic(): void
    {
        $role = new Role();
        $this->assertSame($role, $role->setName('ROLE_USER'));
    }

    public function testGetNameReturnsSetName(): void
    {
        $role = (new Role())->setName('ROLE_PRESTATAIRE');
        $this->assertSame('ROLE_PRESTATAIRE', $role->getName());
    }

    public function testPermissionsDefaultToEmptyArray(): void
    {
        $this->assertSame([], (new Role())->getPermissions());
    }

    public function testSetPermissionsReturnsStatic(): void
    {
        $role = new Role();
        $this->assertSame($role, $role->setPermissions(['read']));
    }

    public function testGetPermissionsReturnsSetPermissions(): void
    {
        $role = (new Role())->setPermissions(['read', 'write']);
        $this->assertSame(['read', 'write'], $role->getPermissions());
    }
}
