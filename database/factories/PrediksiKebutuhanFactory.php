<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PrediksiKebutuhan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrediksiKebutuhan>
 */
class PrediksiKebutuhanFactory extends Factory
{
    protected $model = PrediksiKebutuhan::class;

    public function definition(): array
    {
        return [
            'fasilitas_id' => FasilitasKesehatan::factory(),
            'obat_id' => Obat::factory(),
            'model_id' => null,
            'periode_bulan' => now()->month,
            'periode_tahun' => now()->year,
            'jumlah_prediksi' => $this->faker->numberBetween(10, 500),
            'metode' => 'ANN',
        ];
    }
}
