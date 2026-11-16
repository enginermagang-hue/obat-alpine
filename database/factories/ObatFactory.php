<?php

namespace Database\Factories;

use App\Models\Obat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obat>
 */
class ObatFactory extends Factory
{
    protected $model = Obat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bentuk = fake()->randomElement(['tablet', 'kapsul', 'sirup', 'salep', 'injeksi', 'drop', 'inhaler', 'suppositoria']);

        return [
            'kode_obat' => fake()->unique()->numerify('OBT-#####'),
            'nama_obat' => fake()->words(3, true),
            'nama_generik' => fake()->words(2, true),
            'kategori' => fake()->randomElement(['Analgesik & Antiinflamasi', 'Antibiotik', 'Vitamin & Suplemen', 'Pencernaan']),
            'satuan' => fake()->randomElement(['Tablet', 'Kapsul', 'Botol', 'Tube']),
            'kekuatan' => fake()->randomElement(['500 mg', '250 mg', '100 mg/5 mL', null]),
            'bentuk_sediaan' => $bentuk,
            'produsen' => fake()->company(),
            'kemasan' => fake()->randomElement(['Dus isi 100', 'Strip isi 10', null]),
            'harga_satuan' => fake()->randomFloat(2, 500, 50000),
            'status' => 'aktif',
            'ven_kategori' => fake()->randomElement(['V', 'E', 'N']),
            'metode_stok' => fake()->randomElement(['fefo', 'fifo', 'lifo']),
        ];
    }
}
