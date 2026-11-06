<?php

namespace App\Domain;

/** Objeto de valor: ISBN de exactamente 13 dígitos (CA-03.2). */
final class Isbn
{
    public static function isValid(string $value): bool
    {
        return preg_match('/^\d{13}$/D', $value) === 1;
    }
}
