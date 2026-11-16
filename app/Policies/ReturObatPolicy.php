<?php

namespace App\Policies;

use App\Models\ReturObat;
use App\Models\User;

class ReturObatPolicy
{
    /**
     * Permission: view_retur_obat
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_retur_obat');
    }

    /**
     * Permission: view_retur_obat
     *
     * Super admin & admin dinas: semua retur
     * Admin gudang: retur yang terkait faskesnya (pengirim atau penerima)
     * User (pustu): retur yang dikirim oleh faskesnya
     */
    public function view(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('view_retur_obat')) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('admin_dinas')) {
            return true;
        }

        if ($user->hasRole('admin_gudang')) {
            return $returObat->fasilitas_pengirim_id === null
                || $returObat->fasilitas_penerima_id === null;
        }

        $userFaskesId = $user->fasilitas_kesehatan_id;

        if (blank($userFaskesId)) {
            return false;
        }

        return $returObat->fasilitas_pengirim_id === $userFaskesId
            || $returObat->fasilitas_penerima_id === $userFaskesId;
    }

    /**
     * Permission: create_retur_obat
     *
     * Super admin: bisa membuat semua tipe
     * Admin gudang: bisa membuat puskesmas_ke_gudang dan gudang_ke_supplier
     * Admin dinas: bisa membuat untuk dinas (terbatas)
     * User (pustu): hanya bisa membuat pustu_ke_puskesmas
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin_gudang')) {
            return true;
        }

        if (blank($user->fasilitas_kesehatan_id)) {
            return false;
        }

        return $user->hasPermissionTo('create_retur_obat');
    }

    /**
     * Permission: update_retur_obat
     *
     * Super admin: semua
     * Admin gudang:
     *   - Sebagai pengirim: hanya retur miliknya, status draft/menunggu_approval
     *   - Sebagai penerima: bisa konfirmasi terima (dalam_pengiriman → diterima)
     * Admin dinas: bisa menyetujui/menolak (menunggu_approval → disetujui/ditolak)
     * User (pustu): hanya retur miliknya sendiri, status draft/menunggu_approval
     */
    public function update(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin_dinas')) {
            return $returObat->status === 'menunggu_approval';
        }

        if ($user->hasRole('admin_gudang')) {
            if ($returObat->fasilitas_pengirim_id === null) {
                return in_array($returObat->status, ['draft', 'menunggu_approval'], true);
            }

            if ($returObat->fasilitas_penerima_id === null) {
                return $returObat->status === 'dalam_pengiriman';
            }

            return false;
        }

        $userFaskesId = $user->fasilitas_kesehatan_id;

        if (blank($userFaskesId)) {
            return false;
        }

        // Admin gudang / User: sebagai pengirim (edit draft/menunggu_approval)
        if ($returObat->fasilitas_pengirim_id === $userFaskesId) {
            return in_array($returObat->status, ['draft', 'menunggu_approval'], true);
        }

        // Admin gudang / User: sebagai penerima (konfirmasi terima)
        if ($returObat->fasilitas_penerima_id === $userFaskesId) {
            return $returObat->status === 'dalam_pengiriman';
        }

        return false;
    }

    /**
     * Permission: delete_retur_obat
     *
     * Super admin: semua
     * Admin gudang: hanya retur miliknya sendiri, status draft
     * Admin dinas: tidak bisa menghapus
     * User (pustu): hanya retur miliknya sendiri, status draft
     */
    public function delete(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('delete_retur_obat')) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('admin_gudang')) {
            return $returObat->fasilitas_pengirim_id === null
                && $returObat->status === 'draft';
        }

        $userFaskesId = $user->fasilitas_kesehatan_id;

        return filled($userFaskesId)
            && $returObat->fasilitas_pengirim_id === $userFaskesId
            && $returObat->status === 'draft';
    }

    /**
     * Ability: ajukan (draft → menunggu_approval). Pengirim atau super_admin.
     */
    public function submit(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($returObat->status !== 'draft') {
            return false;
        }

        return $this->isPengirim($user, $returObat) || $user->hasRole('super_admin');
    }

    /**
     * Ability: setujui/tolak (menunggu_approval → disetujui/ditolak).
     * Dinas, gudang (global), atau faskes penerima.
     */
    public function approve(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($returObat->status !== 'menunggu_approval') {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('admin_dinas')) {
            return true;
        }

        return $this->isPenerima($user, $returObat);
    }

    /**
     * Ability: kirim (disetujui → dalam_pengiriman). Pengirim atau super_admin.
     */
    public function send(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($returObat->status !== 'disetujui') {
            return false;
        }

        return $this->isPengirim($user, $returObat) || $user->hasRole('super_admin');
    }

    /**
     * Ability: terima (dalam_pengiriman → diterima). Penerima atau super_admin.
     */
    public function receive(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($returObat->status !== 'dalam_pengiriman') {
            return false;
        }

        return $this->isPenerima($user, $returObat) || $user->hasRole('super_admin');
    }

    /**
     * Ability: selesaikan (diterima → selesai). Sama seperti terima.
     */
    public function complete(User $user, ReturObat $returObat): bool
    {
        if (! $user->hasPermissionTo('update_retur_obat')) {
            return false;
        }

        if ($returObat->status !== 'diterima') {
            return false;
        }

        return $this->isPenerima($user, $returObat) || $user->hasRole('super_admin');
    }

    /**
     * User adalah pihak pengirim: faskesnya sama, atau pengirim gudang (null)
     * dan user adalah admin global tanpa faskes.
     */
    private function isPengirim(User $user, ReturObat $returObat): bool
    {
        if ($returObat->fasilitas_pengirim_id !== null) {
            return filled($user->fasilitas_kesehatan_id)
                && $returObat->fasilitas_pengirim_id === $user->fasilitas_kesehatan_id;
        }

        return blank($user->fasilitas_kesehatan_id)
            && ($user->hasRole('admin_gudang') || $user->hasRole('admin_dinas'));
    }

    /**
     * User adalah pihak penerima: faskesnya sama, atau penerima gudang (null)
     * dan user adalah admin global tanpa faskes.
     */
    private function isPenerima(User $user, ReturObat $returObat): bool
    {
        if ($returObat->fasilitas_penerima_id !== null) {
            return filled($user->fasilitas_kesehatan_id)
                && $returObat->fasilitas_penerima_id === $user->fasilitas_kesehatan_id;
        }

        return blank($user->fasilitas_kesehatan_id)
            && ($user->hasRole('admin_gudang') || $user->hasRole('admin_dinas'));
    }
}
