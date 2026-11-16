<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'PT '.fake()->unique()->company(),
            'alamat' => fake()->address(),
            'telepon' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'npwp' => fake()->numerify('##.###.###.#-###.###'),
            'status' => 'aktif',
        ];
    }
}
