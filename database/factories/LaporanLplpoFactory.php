<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\LaporanLplpo;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaporanLplpo>
 */
class LaporanLplpoFactory extends Factory
{
    protected $model = LaporanLplpo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_id' => FasilitasKesehatan::factory(),
            'periode_bulan' => fake()->numberBetween(1, 12),
            'periode_tahun' => (int) now()->format('Y'),
            'status' => 'draft',
            'tanggal_pembuatan' => now()->toDateString(),
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function withDetail(int $count = 2): static
    {
        return $this->afterCreating(function (LaporanLplpo $laporan) use ($count): void {
            foreach (range(1, $count) as $i) {
                $obat = Obat::factory()->create();
                $stokAwal = fake()->numberBetween(0, 100);
                $masuk = fake()->numberBetween(0, 50);
                $keluar = fake()->numberBetween(0, 50);
                $laporan->details()->create([
                    'obat_id' => $obat->id,
                    'stok_awal' => $stokAwal,
                    'jumlah_masuk' => $masuk,
                    'jumlah_keluar' => $keluar,
                    'sisa_stok' => $stokAwal + $masuk - $keluar,
                    'permintaan_selanjutnya' => fake()->numberBetween(10, 100),
                ]);
            }
        });
    }
}
