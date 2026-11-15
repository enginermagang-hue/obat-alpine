<?php

namespace App\Services;

use App\Models\BatchStok;
use App\Models\PengaturanLaporan;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Models\TutupBuku;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TutupBukuService
{
    /**
     * Ambil cutoff global (tipe inisial) — batas dummy vs real.
     * Dipakai untuk filter default operasional & AI.
     */
    public function getCutoffInisial(): ?Carbon
    {
        $row = TutupBuku::where('tipe', 'inisial')
            ->where('status', 'tutup')
            ->orderByDesc('tahun')
            ->first();

        return $row?->cutoff_at ? Carbon::parse($row->cutoff_at) : null;
    }

    /**
     * Ambil cutoff global terbaru (inisial atau tahunan) untuk filter default.
     */
    public function getCutoffGlobal(): ?Carbon
    {
        $row = TutupBuku::where('status', 'tutup')
            ->orderByDesc('cutoff_at')
            ->first();

        return $row?->cutoff_at ? Carbon::parse($row->cutoff_at) : null;
    }

    /**
     * Apakah tahun tertentu sudah terkunci (tutup buku tahunan sudah tutup)?
     * Serentak global — cek fasilitas_id = NULL.
     */
    public function isTahunTerkunci(int $tahun): bool
    {
        return TutupBuku::where('tahun', $tahun)
            ->where('status', 'tutup')
            ->exists();
    }

    /**
     * Apakah sebuah tanggal terkunci untuk mutasi?
     * - Jika belum ada tutup buku: tidak terkunci.
     * - Jika tanggal < cutoff inisial: terkunci (arsip dummy).
     * - Jika tanggal berada di tahun yang sudah tutup dan lewat deadline: terkunci.
     * - Jika masih dalam grace period (now <= deadline_at): belum terkunci (boleh koreksi).
     */
    public function isTanggalTerkunci(Carbon|string $tanggal, ?int $fasilitasId = null): bool
    {
        $date = $tanggal instanceof Carbon ? $tanggal->copy()->startOfDay() : Carbon::parse($tanggal)->startOfDay();

        // Cek tutup buku inisial (dummy vs real) — selalu terkunci untuk tanggal < cutoff
        $inisial = TutupBuku::where('tipe', 'inisial')
            ->where('status', 'tutup')
            ->orderByDesc('cutoff_at')
            ->first();

        if ($inisial && $date->lt(Carbon::parse($inisial->cutoff_at)->startOfDay())) {
            return true;
        }

        // Cek tutup buku tahunan — serentak global
        $tahun = (int) $date->format('Y');
        $row = TutupBuku::where('tahun', $tahun)
            ->where('tipe', 'tahunan')
            ->where('status', 'tutup')
            ->first();

        if (! $row) {
            return false;
        }

        // Jika masih dalam grace period (deadline belum lewat), izinkan koreksi
        if ($row->deadline_at && now()->lte(Carbon::parse($row->deadline_at))) {
            return false;
        }

        return true;
    }

    /**
     * Validasi sebelum membuat transaksi: throw jika terkunci.
     *
     * @throws \RuntimeException
     */
    public function assertTanggalTidakTerkunci(Carbon|string $tanggal, string $konteks = 'Transaksi'): void
    {
        if ($this->isTanggalTerkunci($tanggal)) {
            $dateStr = $tanggal instanceof Carbon ? $tanggal->format('d/m/Y') : Carbon::parse($tanggal)->format('d/m/Y');
            throw new \RuntimeException("{$konteks} tanggal {$dateStr} terkunci karena sudah tutup buku. Hubungi Super Admin untuk buka kembali.");
        }
    }

    /**
     * Eksekusi cut-off inisial: soft expire batch dummy < cutoff + recalculate + simpan penanda.
     *
     * @return array{expired_batches: int, neraca_id: ?int}
     */
    public function tutupInisial(Carbon|string $cutoff, ?int $userId = null, ?string $catatan = null): array
    {
        $cutoffDate = $cutoff instanceof Carbon ? $cutoff->copy()->startOfDay() : Carbon::parse($cutoff)->startOfDay();
        $tahun = (int) $cutoffDate->format('Y') - 1; // arsip tahun sebelumnya

        if (TutupBuku::where('tipe', 'inisial')->where('status', 'tutup')->exists()) {
            throw new \RuntimeException('Cut-off inisial sudah pernah dilakukan. Gunakan tutup tahunan untuk periode berikutnya.');
        }

        $expiredBatches = 0;

        DB::transaction(function () use ($cutoffDate, $tahun, $userId, $catatan, &$expiredBatches): void {
            // Soft expire semua batch tersedia sebelum cutoff
            $expiredBatches = BatchStok::where('tanggal_masuk', '<', $cutoffDate)
                ->where('status', 'tersedia')
                ->update(['status' => 'expired']);

            // Rekalkulasi agregat
            StokGudang::recalculateAll();
            StokFaskes::recalculateAll();

            // Simpan penanda tutup buku inisial (global)
            TutupBuku::create([
                'fasilitas_id' => null,
                'tahun' => $tahun,
                'tipe' => 'inisial',
                'cutoff_at' => $cutoffDate,
                'deadline_at' => null,
                'status' => 'tutup',
                'ditutup_oleh' => $userId ?? auth()->id(),
                'catatan' => $catatan ?? 'Cut-off inisial dummy -> real',
            ]);

            // Simpan ke pengaturan_laporan untuk filter default UI
            PengaturanLaporan::updateOrCreate(
                ['fasilitas_id' => null, 'grup' => 'cutoff', 'key' => 'tanggal'],
                ['value' => $cutoffDate->format('Y-m-d')]
            );
        });

        return ['expired_batches' => $expiredBatches, 'cutoff' => $cutoffDate->format('Y-m-d')];
    }

    /**
     * Tutup buku tahunan serentak global.
     */
    public function tutupTahun(int $tahun, ?int $neracaId = null, ?int $userId = null, ?string $catatan = null, ?Carbon $deadline = null): TutupBuku
    {
        if ($this->isTahunTerkunci($tahun)) {
            throw new \RuntimeException("Tahun {$tahun} sudah tutup buku.");
        }

        $cutoff = Carbon::create($tahun, 12, 31)->startOfDay();
        $deadlineAt = $deadline ?? Carbon::create($tahun + 1, 1, 15, 23, 59, 59);

        return DB::transaction(function () use ($tahun, $cutoff, $deadlineAt, $neracaId, $userId, $catatan): TutupBuku {
            $row = TutupBuku::create([
                'fasilitas_id' => null,
                'tahun' => $tahun,
                'tipe' => 'tahunan',
                'cutoff_at' => $cutoff,
                'deadline_at' => $deadlineAt,
                'neraca_id' => $neracaId,
                'status' => 'tutup',
                'ditutup_oleh' => $userId ?? auth()->id(),
                'catatan' => $catatan,
            ]);

            return $row;
        });
    }

    /**
     * Buka kembali tahun yang sudah tutup (hanya super_admin).
     */
    public function bukaKembali(TutupBuku $tutupBuku, string $alasan, ?int $userId = null): TutupBuku
    {
        if ($tutupBuku->status !== 'tutup') {
            throw new \RuntimeException('Hanya tutup buku dengan status tutup yang bisa dibuka kembali.');
        }

        $tutupBuku->update([
            'status' => 'dibuka_kembali',
            'catatan' => ($tutupBuku->catatan ? $tutupBuku->catatan."\n\n" : '').'[DIBUKA KEMBALI '.now()->format('d/m/Y H:i').' oleh user #'.($userId ?? auth()->id()).'] Alasan: '.$alasan,
        ]);

        return $tutupBuku->fresh();
    }

    /**
     * Tutup kembali setelah dibuka.
     */
    public function tutupKembali(TutupBuku $tutupBuku, ?int $userId = null): TutupBuku
    {
        if ($tutupBuku->status !== 'dibuka_kembali') {
            throw new \RuntimeException('Hanya yang berstatus dibuka_kembali yang bisa ditutup kembali.');
        }

        $tutupBuku->update([
            'status' => 'tutup',
            'ditutup_oleh' => $userId ?? auth()->id(),
        ]);

        return $tutupBuku->fresh();
    }

    /**
     * Preview dampak inisial tanpa eksekusi.
     *
     * @return array{batch_tersedia_akan_expired: int, stok_gudang_items: int, stok_faskes_items: int}
     */
    public function previewInisial(Carbon|string $cutoff): array
    {
        $cutoffDate = $cutoff instanceof Carbon ? $cutoff->copy()->startOfDay() : Carbon::parse($cutoff)->startOfDay();

        $batchCount = BatchStok::where('tanggal_masuk', '<', $cutoffDate)
            ->where('status', 'tersedia')
            ->count();

        $gudangItems = StokGudang::where('jumlah', '>', 0)->count();
        $faskesItems = StokFaskes::where('jumlah', '>', 0)->count();

        return [
            'cutoff' => $cutoffDate->format('Y-m-d'),
            'batch_tersedia_akan_expired' => $batchCount,
            'stok_gudang_items' => $gudangItems,
            'stok_faskes_items' => $faskesItems,
        ];
    }

    /**
     * Apakah toggle arsip boleh ditampilkan untuk user ini?
     */
    public function bolehLihatArsip(?User $user = null): bool
    {
        $u = $user ?? auth()->user();

        if (! $u) {
            return false;
        }

        return $u->hasRole(['super_admin', 'admin_dinas', 'admin_gudang']) || $u->hasPermissionTo('view_tutup_buku');
    }
}
