<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/** Token JWT HS256 de 8 horas con solo `sub` y `role` (api-contract §4, D-11). */
final class JwtService
{
    /** @return array{0:string,1:int} [token, expires_in] */
    public function issue(User $user): array
    {
        $ttl = (int) config('library.jwt_ttl');
        $now = time();
        $token = JWT::encode(
            ['sub' => $user->id, 'role' => $user->role, 'iat' => $now, 'exp' => $now + $ttl],
            $this->secret(),
            'HS256'
        );

        return [$token, $ttl];
    }

    public function verify(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    private function secret(): string
    {
        $secret = config('library.jwt_secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET debe definirse en el entorno con al menos 32 caracteres.');
        }

        return $secret;
    }
}
