<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Exige token Bearer válido (CA-01.3). Deja el usuario en $request->attributes['auth_user']. */
final class JwtAuth
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (! $token) {
            throw ApiException::unauthenticated();
        }

        try {
            $claims = $this->jwt->verify($token);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException) {
            throw ApiException::unauthenticated('El token es inválido o venció.');
        }

        $sub = $claims->sub ?? null;
        if (! is_string($sub) || ! Str::isUuid($sub) || ! ($user = User::find($sub))) {
            throw ApiException::unauthenticated('El token es inválido o venció.');
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
