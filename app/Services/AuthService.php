<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** HU-01 y HU-02. */
final class AuthService
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    /** @return array{access_token:string,token_type:string,expires_in:int,role:string} */
    public function login(string $username, string $password): array
    {
        $user = User::query()->where('username', self::normalize($username))->first();

        // Se compara siempre contra un hash para no revelar si falló el usuario o la clave (CA-01.2).
        $hash = $user?->password_hash ?? Hash::make(Str::random(16));
        $ok = Hash::check($password, $hash);

        if (! $user || ! $ok) {
            throw ApiException::invalidCredentials();
        }

        [$token, $ttl] = $this->jwt->issue($user);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'role' => $user->role];
    }

    public function createLibrarian(string $username, string $password): User
    {
        $username = self::normalize($username);

        if (User::query()->where('username', $username)->exists()) {
            throw self::usernameTaken();
        }

        try {
            return User::create([
                'username' => $username,
                'password_hash' => Hash::make($password),
                'role' => 'librarian', // el rol admin nunca se otorga por la API (CA-02.3)
            ]);
        } catch (UniqueConstraintViolationException) {
            throw self::usernameTaken();
        }
    }

    public static function normalize(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    private static function usernameTaken(): ApiException
    {
        return ApiException::conflict('USERNAME_TAKEN', 'Ya existe un usuario con ese nombre.');
    }
}
