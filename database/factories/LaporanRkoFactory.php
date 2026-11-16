<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\LaporanRko;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaporanRko>
 */
class LaporanRkoFactory extends Factory
{
    protected $model = LaporanRko::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_id' => FasilitasKesehatan::factory(),
            'periode_tahun' => (int) now()->format('Y') + 1,
            'status' => 'draft',
            'tanggal_pembuatan' => now()->toDateString(),
            'dibuat_oleh' => User::factory(),
        ];
    }

    public function withDetail(int $count = 2): static
    {
        return $this->afterCreating(function (LaporanRko $rko) use ($count): void {
            foreach (range(1, $count) as $i) {
                $obat = Obat::factory()->create();
                $rko->details()->create([
                    'obat_id' => $obat->id,
                    'pemakaian_tahun_sebelumnya' => fake()->numberBetween(100, 1000),
                    'rata_rata_pemakaian_bulanan' => fake()->numberBetween(10, 100),
                    'stok_akhir' => fake()->numberBetween(0, 200),
                    'kebutuhan_tahunan' => fake()->numberBetween(100, 1200),
                    'usulan' => fake()->numberBetween(100, 1200),
                    'total_kebutuhan' => fake()->numberBetween(100, 1200),
                    'rencana_kebutuhan' => fake()->numberBetween(100, 1200),
                ]);
            }
        });
    }
}
