<?php

namespace Database\Factories;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\ReturObat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturObat>
 */
class ReturObatFactory extends Factory
{
    protected $model = ReturObat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_pengirim_id' => FasilitasKesehatan::factory(),
            'fasilitas_penerima_id' => null,
            'tipe_retur' => 'puskesmas_ke_gudang',
            'alasan' => 'expired',
            'status' => 'draft',
            'tanggal_retur' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }

    public function withDetail(int $count = 1): static
    {
        return $this->afterCreating(function (ReturObat $retur) use ($count): void {
            foreach (range(1, $count) as $i) {
                $obat = Obat::factory()->create();
                $batch = BatchStok::factory()->create([
                    'obat_id' => $obat->id,
                    'fasilitas_id' => $retur->fasilitas_pengirim_id,
                    'jumlah' => 100,
                    'status' => 'tersedia',
                ]);

                $retur->details()->create([
                    'obat_id' => $obat->id,
                    'batch_id' => $batch->id,
                    'jumlah_retur' => fake()->numberBetween(5, 50),
                ]);
            }
        });
    }
}
