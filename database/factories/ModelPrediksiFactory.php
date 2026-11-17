<?php

namespace Database\Factories;

use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelPrediksi>
 */
class ModelPrediksiFactory extends Factory
{
    protected $model = ModelPrediksi::class;

    public function definition(): array
    {
        return [
            'fasilitas_id' => FasilitasKesehatan::factory(),
            'obat_id' => Obat::factory(),
            'model_data' => '{}',
            'akurasi_r2' => $this->faker->randomFloat(4, 0.5, 0.95),
            'mae' => $this->faker->randomFloat(2, 1, 20),
            'mape' => $this->faker->randomFloat(2, 5, 30),
            'tanggal_training' => now()->subDays(rand(0, 30)),
            'data_training_count' => 12,
            'status' => 'aktif',
        ];
    }
}
