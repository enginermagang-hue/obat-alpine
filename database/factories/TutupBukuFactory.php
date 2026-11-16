<?php

namespace Database\Factories;

use App\Models\TutupBuku;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutupBuku>
 */
class TutupBukuFactory extends Factory
{
    protected $model = TutupBuku::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fasilitas_id' => null,
            'tahun' => (int) now()->format('Y'),
            'tipe' => 'tahunan',
            'cutoff_at' => now()->toDateString(),
            'status' => 'tutup',
            'ditutup_oleh' => User::factory(),
        ];
    }
}
