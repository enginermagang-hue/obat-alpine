<?php

namespace App\Policies;

use App\Models\TutupBuku;
use App\Models\User;

class TutupBukuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_tutup_buku');
    }

    public function view(User $user, TutupBuku $tutupBuku): bool
    {
        return $user->hasPermissionTo('view_tutup_buku');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_tutup_buku') && ($user->hasRole('super_admin') || $user->hasRole('admin_gudang'));
    }

    public function update(User $user, TutupBuku $tutupBuku): bool
    {
        return $user->hasPermissionTo('update_tutup_buku') && $user->hasRole('super_admin');
    }

    public function delete(User $user, TutupBuku $tutupBuku): bool
    {
        return $user->hasRole('super_admin');
    }
}
