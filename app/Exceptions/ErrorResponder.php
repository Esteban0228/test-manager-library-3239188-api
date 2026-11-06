<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Único lugar que arma el cuerpo de error { code, message, details } (api-contract §1). */
final class ErrorResponder
{
    private const ISSUES = [
        'Required' => 'obligatorio',
        'String' => 'debe ser texto',
        'Integer' => 'debe ser un entero',
        'Uuid' => 'identificador inválido',
        'Regex' => 'formato inválido',
        'In' => 'valor no permitido',
        'Min' => 'por debajo del mínimo',
        'Max' => 'por encima del máximo',
        'Array' => 'debe ser una lista',
        'DateFormat' => 'fecha inválida, usa AAAA-MM-DD',
        'File' => 'archivo inválido',
        'Mimetypes' => 'tipo de archivo no permitido',
        'Uploaded' => 'la subida falló o supera el tamaño permitido',
    ];

    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ApiException) {
            return self::body($e->status, $e->errorCode, $e->getMessage(), $e->details);
        }

        if ($e instanceof ValidationException) {
            return self::body(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', self::validationDetails($e));
        }

        if ($e instanceof AuthenticationException) {
            return self::body(401, 'UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.');
        }

        if ($e instanceof HttpExceptionInterface) {
            return match ($e->getStatusCode()) {
                401 => self::body(401, 'UNAUTHENTICATED', 'Debes iniciar sesión con un token válido.'),
                403 => self::body(403, 'FORBIDDEN_ROLE', 'Tu rol no permite esta operación.'),
                404 => self::body(404, 'NOT_FOUND', 'Recurso no encontrado.'),
                405 => self::body(405, 'METHOD_NOT_ALLOWED', 'Método no permitido para esta ruta.'),
                413 => self::body(422, 'VALIDATION_ERROR', 'La petición supera el tamaño permitido.'),
                default => self::body($e->getStatusCode(), 'HTTP_ERROR', 'No se pudo procesar la solicitud.'),
            };
        }

        // Nunca se filtran trazas, SQL ni rutas del servidor.
        return self::body(500, 'INTERNAL_ERROR', 'Ocurrió un error interno. Intenta de nuevo más tarde.');
    }

    private static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'details' => array_values($details)], $status);
    }

    private static function validationDetails(ValidationException $e): array
    {
        $details = [];
        foreach ($e->validator->failed() as $field => $rules) {
            foreach (array_keys($rules) as $rule) {
                $details[] = ['field' => self::field($field), 'issue' => self::ISSUES[$rule] ?? 'valor inválido'];
            }
        }
        if ($details === []) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $m) {
                    $details[] = ['field' => self::field($field), 'issue' => $m];
                }
            }
        }

        return $details;
    }

    /** items.0.book_id -> items[0].book_id */
    private static function field(string $field): string
    {
        return preg_replace('/\.(\d+)/', '[$1]', $field);
    }
}
