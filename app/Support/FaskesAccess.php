<?php

namespace App\Support;

use App\Models\FasilitasKesehatan;
use App\Models\User;

/**
 * Batas akses data per fasilitas kesehatan.
 *
 * User tanpa faskes (dinas/gudang) dianggap melihat semua; user puskesmas
 * melihat puskesmas-nya + pustu di bawahnya; user pustu hanya pustu-nya.
 */
class FaskesAccess
{
    /**
     * @return array<int, int>
     */
    public static function visibleFasilitasIds(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user || blank($user->fasilitas_kesehatan_id)) {
            return [];
        }

        $ids = [(int) $user->fasilitas_kesehatan_id];
        $faskes = FasilitasKesehatan::find($user->fasilitas_kesehatan_id);

        if ($faskes?->tipe === 'puskesmas') {
            $ids = array_merge(
                $ids,
                $faskes->pustu()->pluck('id')->map(fn ($v): int => (int) $v)->toArray(),
            );
        }

        return array_values(array_unique($ids));
    }
}
