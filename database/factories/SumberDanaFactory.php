<?php

namespace Database\Factories;

use App\Models\SumberDana;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SumberDana>
 */
class SumberDanaFactory extends Factory
{
    protected $model = SumberDana::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('DN-####'),
            'nama' => 'Dana '.fake()->words(2, true),
            'tahun' => (int) now()->format('Y'),
            'total_anggaran' => fake()->randomFloat(2, 10000000, 500000000),
            'status' => 'aktif',
            'keterangan' => null,
        ];
    }
}
