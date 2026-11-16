<?php

namespace Database\Factories;

use App\Models\Obat;
use App\Models\RiwayatStok;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatStok>
 */
class RiwayatStokFactory extends Factory
{
    protected $model = RiwayatStok::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jumlah = fake()->numberBetween(1, 100);

        return [
            'obat_id' => Obat::factory(),
            'tipe' => fake()->randomElement(['masuk', 'keluar', 'opname', 'penyesuaian']),
            'jumlah' => $jumlah,
            'stok_sebelum' => 100,
            'stok_sesudah' => 100 + $jumlah,
            'user_id' => User::factory(),
            'tanggal' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'keterangan' => fake()->sentence(),
        ];
    }
}
