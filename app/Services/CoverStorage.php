<?php

namespace App\Services;

use RuntimeException;

/** Portadas en disco. La base guarda solo una clave opaca, nunca la ruta (D-08, CA-03.4). */
final class CoverStorage
{
    public function put(string $key, string $contents): void
    {
        $dir = $this->dir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de portadas.');
        }
        file_put_contents($this->path($key), $contents);
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function path(string $key): string
    {
        if (preg_match('/^[0-9a-f\-]{36}\.(png|jpg)$/', $key) !== 1) {
            throw new RuntimeException('Clave de portada inválida.');
        }

        return $this->dir().DIRECTORY_SEPARATOR.$key;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function dir(): string
    {
        return rtrim((string) config('library.covers_dir'), '/\\');
    }
}
