<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Bentuk meta pagination yang konsisten untuk tabel Alpine (server-backed).
 */
class TableMeta
{
    /**
     * @return array{page: int, per_page: int, total: int, last_page: int, from: ?int, to: ?int}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
