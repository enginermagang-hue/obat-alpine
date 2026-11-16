<?php

namespace Database\Factories;

use App\Models\NeracaTahunan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NeracaTahunan>
 */
class NeracaTahunanFactory extends Factory
{
    protected $model = NeracaTahunan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_id' => null,
            'tahun' => (int) now()->format('Y'),
            'status' => 'draft',
            'dibuat_oleh' => User::factory(),
        ];
    }
}
