<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FasilitasKesehatan>
 */
class FasilitasKesehatanFactory extends Factory
{
    protected $model = FasilitasKesehatan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_faskes' => fake()->unique()->numerify('FSK-#####'),
            'nama' => 'Puskesmas '.fake()->city(),
            'tipe' => 'puskesmas',
            'puskesmas_induk_id' => null,
            'alamat' => fake()->address(),
            'pic' => fake()->name(),
            'kontak_pic' => fake()->phoneNumber(),
            'telepon' => fake()->phoneNumber(),
            'kepala_faskes' => 'dr. '.fake()->name(),
            'status' => 'aktif',
        ];
    }

    public function pustu(?int $indukId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'kode_faskes' => fake()->unique()->numerify('PST-#####'),
            'nama' => 'Pustu '.fake()->city(),
            'tipe' => 'pustu',
            'puskesmas_induk_id' => $indukId ?? FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas'])->id,
        ]);
    }
}
