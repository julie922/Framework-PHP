<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JWTService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JWTAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JWTService      $jwtService,
        private readonly UserRepository  $userRepository,
    ) {}

    // Symfony appelle supports() sur chaque requête — on ne s'active que si le header Bearer est là
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token   = substr($request->headers->get('Authorization'), 7);
        $payload = $this->jwtService->decode($token);

        // On vérifie le champ 'type' pour rejeter explicitement les refresh tokens
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            throw new CustomUserMessageAuthenticationException('Token JWT invalide ou expiré.');
        }

        return new SelfValidatingPassport(
            new UserBadge($payload['email'], fn(string $email) => $this->userRepository->findByEmail($email))
        );
    }

    // Retourner null ici indique à Symfony de continuer vers le contrôleur normalement
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'Unauthorized', 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
