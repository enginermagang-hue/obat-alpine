<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PermintaanObat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermintaanObat>
 */
class PermintaanObatFactory extends Factory
{
    protected $model = PermintaanObat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_pengirim_id' => FasilitasKesehatan::factory(),
            'fasilitas_tujuan_id' => null,
            'tipe_permintaan' => 'puskesmas_ke_dinas',
            'status' => 'draft',
            'tanggal_permintaan' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }

    public function withDetail(int $count = 2): static
    {
        return $this->afterCreating(function (PermintaanObat $permintaan) use ($count): void {
            foreach (range(1, $count) as $i) {
                $permintaan->details()->create([
                    'obat_id' => Obat::factory()->create()->id,
                    'jumlah_diminta' => fake()->numberBetween(10, 200),
                ]);
            }
        });
    }
}
