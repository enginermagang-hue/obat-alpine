<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PemakaianObat>
 */
class PemakaianObatFactory extends Factory
{
    protected $model = PemakaianObat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_id' => FasilitasKesehatan::factory(),
            'tanggal_pemakaian' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'jenis_pelayanan' => fake()->randomElement(['rawat_jalan', 'rawat_inap', 'apotek', 'gigi']),
            'user_id' => User::factory(),
        ];
    }

    public function withDetail(int $count = 1): static
    {
        return $this->afterCreating(function (PemakaianObat $pemakaian) use ($count): void {
            foreach (range(1, $count) as $i) {
                $pemakaian->details()->create([
                    'obat_id' => Obat::factory()->create()->id,
                    'jumlah' => fake()->numberBetween(5, 50),
                    'dosis' => '3x1',
                ]);
            }
        });
    }
}
