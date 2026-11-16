<?php

namespace App\Policies;

use App\Models\BatchStok;
use App\Models\User;
use App\Support\FaskesAccess;

class BatchStokPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_batch_stok');
    }

    public function view(User $user, BatchStok $batchStok): bool
    {
        if (! $user->hasPermissionTo('view_batch_stok')) {
            return false;
        }

        // User faskes hanya melihat batch miliknya (faskes + pustu di bawahnya).
        if (filled($user->fasilitas_kesehatan_id)) {
            return in_array($batchStok->fasilitas_id, FaskesAccess::visibleFasilitasIds($user), true);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BatchStok $batchStok): bool
    {
        return false;
    }

    public function delete(User $user, BatchStok $batchStok): bool
    {
        return false;
    }
}
