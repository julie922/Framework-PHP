<?php

namespace App\Tests\Unit\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Service\JWTService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour JWTService.
 *
 * Aucune dépendance Symfony/Doctrine — on instancie directement le service.
 */
class JWTServiceTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JWTService('super_secret_key_for_unit_tests', 1800, 604800);
    }

    // ------------------------------------------------------------------
    // Access token
    // ------------------------------------------------------------------

    public function testGenerateAccessTokenReturnsTrePartString(): void
    {
        $token  = $this->jwt->generateAccessToken($this->makeUser());
        $parts  = explode('.', $token);

        $this->assertCount(3, $parts, 'Un JWT doit contenir exactement 3 parties séparées par "."');
    }

    public function testDecodeAccessTokenReturnsCorrectPayload(): void
    {
        $user    = $this->makeUser('alice@test.com');
        $token   = $this->jwt->generateAccessToken($user);
        $payload = $this->jwt->decode($token);

        $this->assertIsArray($payload);
        $this->assertSame($user->getId(),    $payload['sub']);
        $this->assertSame($user->getEmail(), $payload['email']);
        $this->assertSame('access',          $payload['type']);
        $this->assertArrayHasKey('exp',      $payload);
        $this->assertArrayHasKey('iat',      $payload);
        $this->assertGreaterThan(time(),     $payload['exp']);
    }

    public function testAccessTokenContainsUserRoles(): void
    {
        $user    = $this->makeUser('bob@test.com');
        $token   = $this->jwt->generateAccessToken($user);
        $payload = $this->jwt->decode($token);

        $this->assertContains('ROLE_USER', $payload['roles']);
    }

    public function testDecodeReturnsTtlFromGetAccessTtl(): void
    {
        $this->assertSame(1800, $this->jwt->getAccessTtl());
    }

    // ------------------------------------------------------------------
    // Refresh token
    // ------------------------------------------------------------------

    public function testGenerateRefreshTokenHasTypeRefresh(): void
    {
        $user    = $this->makeUser();
        $token   = $this->jwt->generateRefreshToken($user);
        $payload = $this->jwt->decode($token);

        $this->assertIsArray($payload);
        $this->assertSame('refresh', $payload['type']);
    }

    public function testRefreshTokenHasJti(): void
    {
        $user    = $this->makeUser();
        $token   = $this->jwt->generateRefreshToken($user);
        $payload = $this->jwt->decode($token);

        $this->assertArrayHasKey('jti', $payload);
        $this->assertNotEmpty($payload['jti']);
    }

    public function testTwoRefreshTokensHaveDifferentJti(): void
    {
        $user   = $this->makeUser();
        $token1 = $this->jwt->generateRefreshToken($user);
        $token2 = $this->jwt->generateRefreshToken($user);

        $jti1 = $this->jwt->decode($token1)['jti'];
        $jti2 = $this->jwt->decode($token2)['jti'];

        $this->assertNotSame($jti1, $jti2, 'Chaque refresh token doit avoir un jti unique');
    }

    // ------------------------------------------------------------------
    // Sécurité
    // ------------------------------------------------------------------

    public function testTamperedSignatureReturnsNull(): void
    {
        $user  = $this->makeUser();
        $token = $this->jwt->generateAccessToken($user);

        // On remplace la signature par une valeur aléatoire
        $parts         = explode('.', $token);
        $parts[2]      = 'invalide_signature_xyz';
        $tampered      = implode('.', $parts);

        $this->assertNull($this->jwt->decode($tampered));
    }

    public function testMalformedTokenReturnsNull(): void
    {
        $this->assertNull($this->jwt->decode('pasunjwt'));
        $this->assertNull($this->jwt->decode('deux.parties'));
        $this->assertNull($this->jwt->decode(''));
    }

    public function testExpiredTokenReturnsNull(): void
    {
        // On crée un service avec TTL de -1 seconde → token déjà expiré
        $jwtExpired = new JWTService('super_secret_key_for_unit_tests', -1, -1);
        $user       = $this->makeUser();
        $token      = $jwtExpired->generateAccessToken($user);

        $this->assertNull($this->jwt->decode($token), 'Un token expiré doit retourner null');
    }

    public function testRefreshTokenRejectedWhenTypeIsRefresh(): void
    {
        // Le JWTAuthenticator rejette les refresh tokens (type != 'access').
        // On vérifie ici que decode() renvoie bien le payload (la vérification du type
        // est faite par l'authenticator), et que le champ type est bien 'refresh'.
        $user    = $this->makeUser();
        $refresh = $this->jwt->generateRefreshToken($user);
        $payload = $this->jwt->decode($refresh);

        $this->assertIsArray($payload);
        $this->assertNotSame('access', $payload['type'],
            'Un refresh token ne doit pas avoir type=access');
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function makeUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email)
             ->setFirstName('Test')
             ->setLastName('User')
             ->setPassword('hashed_password');

        return $user;
    }
}
