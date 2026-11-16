<?php

namespace Database\Factories;

use App\Models\Obat;
use App\Models\PenerimaanStok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenerimaanStok>
 */
class PenerimaanStokFactory extends Factory
{
    protected $model = PenerimaanStok::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'user_id' => \App\Models\User::factory(),
            'status' => 'draft',
            'total_biaya' => 0,
        ];
    }

    public function withDetail(int $count = 2): static
    {
        return $this->afterCreating(function (PenerimaanStok $penerimaan) use ($count): void {
            $total = 0;

            foreach (range(1, $count) as $i) {
                $obat = Obat::factory()->create();
                $jumlah = fake()->numberBetween(10, 100);
                $harga = fake()->randomFloat(2, 1000, 20000);
                $total += $jumlah * $harga;

                $penerimaan->details()->create([
                    'obat_id' => $obat->id,
                    'batch_number' => fake()->unique()->bothify('BTH-#####'),
                    'tanggal_expired' => fake()->dateTimeBetween('+6 months', '+3 years')->format('Y-m-d'),
                    'jumlah' => $jumlah,
                    'harga_satuan' => $harga,
                    'sub_total' => $jumlah * $harga,
                ]);
            }

            $penerimaan->update(['total_biaya' => $total]);
        });
    }
}
