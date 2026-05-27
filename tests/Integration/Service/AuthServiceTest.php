<?php

namespace App\Tests\Integration\Service;

use App\Service\AuthService;
use App\Tests\Integration\AbstractIntegrationTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration pour AuthService.
 *
 * Couvre l'inscription, la connexion et le renouvellement de token.
 */
class AuthServiceTest extends AbstractIntegrationTestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = self::getContainer()->get(AuthService::class);
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function testRegisterCreatesUserWithCorrectData(): void
    {
        $user = $this->authService->register([
            'email'     => 'alice@test.com',
            'password'  => 'Password123!',
            'firstName' => 'Alice',
            'lastName'  => 'Dupont',
            'roles'     => ['ROLE_USER'],
        ]);

        $this->assertNotEmpty($user->getId());
        $this->assertSame('alice@test.com', $user->getEmail());
        $this->assertSame('Alice',          $user->getFirstName());
        $this->assertSame('Dupont',         $user->getLastName());
        $this->assertContains('ROLE_USER',  $user->getRoles());
    }

    public function testRegisterHashesPassword(): void
    {
        $user = $this->authService->register([
            'email'     => 'bob@test.com',
            'password'  => 'Password123!',
            'firstName' => 'Bob',
            'lastName'  => 'Martin',
        ]);

        // Le mot de passe stocké doit être non vide
        $this->assertNotEmpty($user->getPassword());

        // Le hasher doit valider le mot de passe original (fonctionne aussi avec plaintext en env test)
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($user, 'Password123!'),
            'Le mot de passe doit être vérifiable via le PasswordHasher');
    }

    public function testRegisterWithDefaultRoleUser(): void
    {
        $user = $this->authService->register([
            'email'     => 'carol@test.com',
            'password'  => 'Password123!',
            'firstName' => 'Carol',
            'lastName'  => 'Dupont',
            // Pas de champ 'roles' → doit assigner ROLE_USER par défaut
        ]);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRegisterWithDuplicateEmailThrowsException(): void
    {
        $data = [
            'email'     => 'duplic@test.com',
            'password'  => 'Password123!',
            'firstName' => 'Test',
            'lastName'  => 'User',
        ];

        $this->authService->register($data);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/déjà utilisé/');

        $this->authService->register($data); // même email → doit lever une exception
    }

    public function testRegisterWithUnknownRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Rôle inconnu/');

        $this->authService->register([
            'email'     => 'test@test.com',
            'password'  => 'Password123!',
            'firstName' => 'Test',
            'lastName'  => 'User',
            'roles'     => ['ROLE_INEXISTANT'],
        ]);
    }

    // ------------------------------------------------------------------
    // login()
    // ------------------------------------------------------------------

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $this->authService->register([
            'email'    => 'alice@test.com',
            'password' => 'Password123!',
            'firstName'=> 'Alice',
            'lastName' => 'Dupont',
        ]);

        $result = $this->authService->login('alice@test.com', 'Password123!');

        $this->assertArrayHasKey('accessToken',  $result);
        $this->assertArrayHasKey('refreshToken', $result);
        $this->assertArrayHasKey('expiresIn',    $result);
        $this->assertArrayHasKey('user',         $result);
        $this->assertNotEmpty($result['accessToken']);
        $this->assertNotEmpty($result['refreshToken']);
    }

    public function testLoginWithWrongPasswordThrowsException(): void
    {
        $this->authService->register([
            'email'    => 'alice@test.com',
            'password' => 'Password123!',
            'firstName'=> 'Alice',
            'lastName' => 'Dupont',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/incorrect/');

        $this->authService->login('alice@test.com', 'MauvaisMotDePasse!');
    }

    public function testLoginWithUnknownEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->authService->login('inconnu@test.com', 'Password123!');
    }

    public function testLoginReturnsCorrectUserData(): void
    {
        $this->authService->register([
            'email'    => 'alice@test.com',
            'password' => 'Password123!',
            'firstName'=> 'Alice',
            'lastName' => 'Dupont',
        ]);

        $result = $this->authService->login('alice@test.com', 'Password123!');
        $user   = $result['user'];

        $this->assertSame('alice@test.com', $user['email']);
        $this->assertSame('Alice',          $user['firstName']);
        $this->assertArrayNotHasKey('password', $user,
            'Le mot de passe ne doit JAMAIS être retourné dans la réponse login');
    }

    // ------------------------------------------------------------------
    // refresh()
    // ------------------------------------------------------------------

    public function testRefreshWithValidRefreshTokenReturnsNewAccessToken(): void
    {
        $this->authService->register([
            'email'    => 'alice@test.com',
            'password' => 'Password123!',
            'firstName'=> 'Alice',
            'lastName' => 'Dupont',
        ]);

        $loginResult  = $this->authService->login('alice@test.com', 'Password123!');
        $refreshToken = $loginResult['refreshToken'];

        $refreshResult = $this->authService->refresh($refreshToken);

        $this->assertArrayHasKey('accessToken', $refreshResult);
        $this->assertNotEmpty($refreshResult['accessToken']);
    }

    public function testRefreshWithInvalidTokenThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->authService->refresh('token.invalide.xyz');
    }

    public function testRefreshWithAccessTokenThrowsException(): void
    {
        // Un access token ne doit pas être utilisable comme refresh token
        $this->authService->register([
            'email'    => 'alice@test.com',
            'password' => 'Password123!',
            'firstName'=> 'Alice',
            'lastName' => 'Dupont',
        ]);

        $loginResult = $this->authService->login('alice@test.com', 'Password123!');
        $accessToken = $loginResult['accessToken'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalide|non reconnu/');

        $this->authService->refresh($accessToken);
    }
}
