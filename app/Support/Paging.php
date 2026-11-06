<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Paging
{
    /** @return array{0:int,1:int} [page, size] */
    public static function params(Request $request): array
    {
        $v = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return [(int) ($v['page'] ?? 1), (int) ($v['size'] ?? 20)];
    }

    public static function envelope(array $items, int $page, int $size, int $total): array
    {
        return ['items' => $items, 'page' => $page, 'size' => $size, 'total' => $total];
    }
}
