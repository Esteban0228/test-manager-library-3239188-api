<?php

namespace App\Exceptions;

use RuntimeException;

/** Error de negocio o de entrada; solo ErrorResponder lo traduce al cuerpo único (RNF-4). */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function unauthenticated(string $message = 'Debes iniciar sesión con un token válido.'): self
    {
        return new self(401, 'UNAUTHENTICATED', $message);
    }

    public static function invalidCredentials(): self
    {
        return new self(401, 'INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
    }

    public static function forbidden(): self
    {
        return new self(403, 'FORBIDDEN_ROLE', 'Tu rol no permite esta operación.');
    }

    public static function notFound(string $what): self
    {
        return new self(404, 'NOT_FOUND', "{$what} no encontrado.");
    }

    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self(409, $code, $message, $details);
    }

    public static function unprocessable(string $code, string $message, array $details = []): self
    {
        return new self(422, $code, $message, $details);
    }
}
