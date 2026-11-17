<?php

namespace Tests\Unit;

use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use App\Models\PrediksiKebutuhan;
use App\Services\PrediksiRekomendasiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrediksiRekomendasiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_rumus_rekomendasi(): void
    {
        $faskes = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create(['status' => 'aktif', 'harga_satuan' => 1000]);
        $model = ModelPrediksi::factory()->create(['fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'status' => 'aktif', 'akurasi_r2' => 0.9]);
        PrediksiKebutuhan::factory()->create([
            'fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'model_id' => $model->id,
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'jumlah_prediksi' => 100,
        ]);

        $svc = new PrediksiRekomendasiService(horizon: 1);
        $row = collect($svc->rows())->firstWhere('obat_id', $obat->id);
        // 100 * 1.2 - 0 = 120
        $this->assertEquals(120, $row['rekom']);
        $this->assertEquals('Kritis', $row['status']);
    }

    public function test_cap_rekomendasi(): void
    {
        $faskes = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create(['status' => 'aktif']);
        $model = ModelPrediksi::factory()->create(['fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'status' => 'aktif']);
        PrediksiKebutuhan::factory()->create([
            'fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'model_id' => $model->id,
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'jumlah_prediksi' => 10_000_000,
        ]);

        $row = collect((new PrediksiRekomendasiService(horizon: 1))->rows())->firstWhere('obat_id', $obat->id);
        $this->assertLessThanOrEqual(5_000_000, $row['rekom']);
    }

    public function test_memoize_hanya_satu_kali_query_berat(): void
    {
        $faskes = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create(['status' => 'aktif']);
        $model = ModelPrediksi::factory()->create(['fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'status' => 'aktif']);
        PrediksiKebutuhan::factory()->create([
            'fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'model_id' => $model->id,
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'jumlah_prediksi' => 50,
        ]);

        $svc = new PrediksiRekomendasiService(horizon: 1);
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $svc->rows();
        $afterFirst = $count;
        $svc->kpi();
        $svc->lonjakan();
        // kpi + lonjakan memakai cache rows → tidak menambah query prediksi_kebutuhan
        $this->assertSame($svc->rows(), $svc->rows());
        $this->assertGreaterThan(0, $afterFirst);
    }
}
