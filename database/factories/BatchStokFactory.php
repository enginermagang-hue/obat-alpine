<?php

namespace Database\Factories;

use App\Models\BatchStok;
use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchStok>
 */
class BatchStokFactory extends Factory
{
    protected $model = BatchStok::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obat_id' => Obat::factory(),
            'batch_number' => fake()->unique()->bothify('BTH-#####'),
            'tanggal_expired' => fake()->dateTimeBetween('+1 month', '+3 years')->format('Y-m-d'),
            'tanggal_masuk' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'jumlah' => fake()->numberBetween(0, 1000),
            'status' => 'tersedia',
        ];
    }
}
