<?php

namespace App\Console\Commands;

use App\Models\TutupBuku;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TutupBukuIngatkanCommand extends Command
{
    protected $signature = 'tutup-buku:ingatkan';

    protected $description = 'Kirim log pengingat tutup buku (01-15 Jan)';

    public function handle(): int
    {
        $now = now();

        if ($now->month !== 1 || $now->day > 15) {
            return self::SUCCESS;
        }

        $tahunLalu = $now->year - 1;
        $existing = TutupBuku::where('tahun', $tahunLalu)->where('status', 'tutup')->exists();

        if ($existing) {
            return self::SUCCESS;
        }

        $deadline = Carbon::create($tahunLalu + 1, 1, 15, 23, 59, 59);
        $sisa = $now->diffInDays($deadline, false);
        $this->line("Pengingat: Tutup Buku {$tahunLalu} deadline {$deadline->format('d/m/Y H:i')} — sisa {$sisa} hari.");

        return self::SUCCESS;
    }
}
