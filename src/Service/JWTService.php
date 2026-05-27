<?php

namespace App\Service;

use App\Entity\User;

// Le format reste 100% compatible avec la spec JWT (RFC 7519).
class JWTService
{
    private string $secret;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(string $jwtSecret, int $accessTtl = 1800, int $refreshTtl = 604800)
    {
        $this->secret     = $jwtSecret;
        $this->accessTtl  = $accessTtl;  // 30 minutes
        $this->refreshTtl = $refreshTtl; // 7 jours
    }

    public function generateAccessToken(User $user): string
    {
        $payload = [
            'sub'   => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat'   => time(),
            'exp'   => time() + $this->accessTtl,
            'type'  => 'access',
        ];

        return $this->encode($payload);
    }

    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'sub'  => $user->getId(),
            'iat'  => time(),
            'exp'  => time() + $this->refreshTtl,
            'type' => 'refresh',
            'jti'  => bin2hex(random_bytes(16)), // identifiant unique pour pouvoir invalider un token précis
        ];

        return $this->encode($payload);
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signature] = $parts;

        $expectedSignature = $this->sign("$headerB64.$payloadB64");
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload) || (isset($payload['exp']) && $payload['exp'] < time())) {
            return null;
        }

        return $payload;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    private function encode(array $payload): string
    {
        $header     = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signature  = $this->sign("$header.$payloadB64");

        return "$header.$payloadB64.$signature";
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
