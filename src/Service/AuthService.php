<?php

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserRepository              $userRepository,
        private readonly RoleRepository              $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTService                 $jwtService,
    ) {}

    public function register(array $data): User
    {
        if ($this->userRepository->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé.');
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $roleNames = $data['roles'] ?? ['ROLE_USER'];
        foreach ($roleNames as $roleName) {
            $role = $this->roleRepository->findByName($roleName);
            if (!$role) {
                throw new \InvalidArgumentException("Rôle inconnu: $roleName");
            }
            $user->addUserRole($role);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new \InvalidArgumentException('Email ou mot de passe incorrect.');
        }

        return $this->issueTokens($user);
    }

    public function refresh(string $refreshToken): array
    {
        $payload = $this->jwtService->decode($refreshToken);

        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            throw new \InvalidArgumentException('Refresh token invalide ou expiré.');
        }

        $user = $this->userRepository->findByRefreshToken($refreshToken);
        if (!$user) {
            throw new \InvalidArgumentException('Refresh token non reconnu.');
        }

        if ($user->getRefreshTokenExpiresAt() < new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Refresh token expiré.');
        }

        return $this->issueTokens($user);
    }

    private function issueTokens(User $user): array
    {
        $accessToken  = $this->jwtService->generateAccessToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        $user->setRefreshToken($refreshToken);
        $user->setRefreshTokenExpiresAt(new \DateTimeImmutable('+7 days'));
        $this->em->flush();

        return [
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn'    => $this->jwtService->getAccessTtl(),
            'user'         => $this->serializeUser($user),
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
        ];
    }
}
