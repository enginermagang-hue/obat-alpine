<?php

namespace App\Console\Commands;

use App\Models\TutupBuku;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TutupBukuAutoCommand extends Command
{
    protected $signature = 'tutup-buku:auto';

    protected $description = 'Auto tutup buku jika lewat deadline 15 Jan (dipanggil scheduler 16 Jan 02:00)';

    public function handle(): int
    {
        $now = now();

        if ($now->month !== 1 || $now->day !== 16) {
            $this->line('Bukan 16 Januari — skip.');

            return self::SUCCESS;
        }

        $tahunLalu = $now->year - 1;
        $existing = TutupBuku::where('tahun', $tahunLalu)->where('status', 'tutup')->exists();

        if ($existing) {
            $this->info("Tahun {$tahunLalu} sudah tutup.");

            return self::SUCCESS;
        }

        // Jika belum ada row, dan deadline lewat, buat auto tutup
        $cutoff = Carbon::create($tahunLalu, 12, 31);
        $deadline = Carbon::create($tahunLalu + 1, 1, 15, 23, 59, 59);

        TutupBuku::create([
            'fasilitas_id' => null,
            'tahun' => $tahunLalu,
            'tipe' => 'tahunan',
            'cutoff_at' => $cutoff,
            'deadline_at' => $deadline,
            'status' => 'tutup',
            'ditutup_oleh' => null,
            'catatan' => 'Auto-tutup oleh scheduler 16 Jan (terlambat)',
        ]);

        $this->info("Auto tutup tahun {$tahunLalu} berhasil.");

        return self::SUCCESS;
    }
}
