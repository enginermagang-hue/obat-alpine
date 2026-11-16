<?php

namespace Database\Factories;

use App\Models\BatchStok;
use App\Models\DistribusiObat;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistribusiObat>
 */
class DistribusiObatFactory extends Factory
{
    protected $model = DistribusiObat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipe_distribusi' => 'dinas_ke_puskesmas',
            'fasilitas_pengirim_id' => null,
            'fasilitas_penerima_id' => FasilitasKesehatan::factory(),
            'status' => 'draft',
            'tanggal_kirim' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'pengirim_id' => User::factory(),
        ];
    }

    public function withDetail(int $count = 1): static
    {
        return $this->afterCreating(function (DistribusiObat $distribusi) use ($count): void {
            foreach (range(1, $count) as $i) {
                $obat = Obat::factory()->create();
                $batch = BatchStok::factory()->create([
                    'obat_id' => $obat->id,
                    'fasilitas_id' => $distribusi->fasilitas_pengirim_id,
                    'jumlah' => 1000,
                    'status' => 'tersedia',
                ]);

                $distribusi->details()->create([
                    'obat_id' => $obat->id,
                    'batch_id' => $batch->id,
                    'jumlah' => fake()->numberBetween(10, 100),
                ]);
            }
        });
    }
}
