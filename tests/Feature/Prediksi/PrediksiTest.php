<?php

namespace Tests\Feature\Prediksi;

use App\Jobs\TrainPrediksiModelJob;
use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use App\Models\PrediksiKebutuhan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrediksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function seedPrediksi(?int $faskesId = null, ?int $obatId = null): array
    {
        $faskes = $faskesId ? FasilitasKesehatan::find($faskesId) : FasilitasKesehatan::factory()->create();
        $obat = $obatId ? Obat::find($obatId) : Obat::factory()->create(['status' => 'aktif']);
        $model = ModelPrediksi::factory()->create([
            'fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'status' => 'aktif', 'akurasi_r2' => 0.8,
        ]);
        PrediksiKebutuhan::factory()->create([
            'fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'model_id' => $model->id,
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'jumlah_prediksi' => 100,
        ]);

        return [$faskes, $obat];
    }

    public function test_dinas_dapat_membuka_halaman_prediksi(): void
    {
        $dinas = $this->userWithRole('admin_dinas');
        $this->actingAs($dinas)->get('/prediksi')->assertOk()->assertSee('Prediksi AI', false);
    }

    public function test_tanpa_permission_ditolak(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/prediksi')->assertForbidden();
        $this->actingAs($user)->get('/prediksi/export')->assertForbidden();
    }

    public function test_scope_faskes_dibatasi(): void
    {
        [$faskesA] = $this->seedPrediksi();
        [$faskesB] = $this->seedPrediksi();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $this->actingAs($user)->get('/prediksi?fasilitas_id='.$faskesB->id)->assertForbidden();
        $this->actingAs($user)->get('/prediksi?fasilitas_id='.$faskesA->id)->assertOk();
    }

    public function test_kontrak_json_tabel(): void
    {
        $dinas = $this->userWithRole('admin_dinas');
        $this->seedPrediksi();

        $res = $this->actingAs($dinas)->getJson('/prediksi');
        $res->assertOk();
        $this->assertArrayHasKey('data', $res->json());
        $this->assertArrayHasKey('meta', $res->json());
    }

    public function test_detail_dan_404_luar_scope(): void
    {
        [$faskesA, $obatA] = $this->seedPrediksi();
        [$faskesB, $obatB] = $this->seedPrediksi();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $this->actingAs($user)->getJson("/prediksi/{$obatA->id}/detail")->assertOk()->assertJsonStructure(['ringkasan', 'bulanan', 'tren', 'factors']);
        $this->actingAs($user)->getJson("/prediksi/{$obatB->id}/detail")->assertNotFound();
    }

    public function test_buat_permintaan_membuat_draft(): void
    {
        [$faskes] = $this->seedPrediksi();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);

        $res = $this->actingAs($user)->post('/prediksi/permintaan');
        $res->assertRedirect();
        $this->assertDatabaseHas('permintaan_obat', ['fasilitas_pengirim_id' => $faskes->id, 'status' => 'draft']);
    }

    public function test_buat_permintaan_dinas_ditolak(): void
    {
        $dinas = $this->userWithRole('admin_dinas');
        $this->actingAs($dinas)->post('/prediksi/permintaan')->assertForbidden();
    }

    public function test_export_mengikuti_scope(): void
    {
        [$faskesA] = $this->seedPrediksi();
        [$faskesB] = $this->seedPrediksi();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $this->actingAs($user)->get('/prediksi/export?fasilitas_id='.$faskesB->id)->assertForbidden();
        $res = $this->actingAs($user)->get('/prediksi/export');
        $res->assertOk();
        $this->assertStringContainsString('sheet', $res->headers->get('content-type'));
    }

    public function test_train_model_dispatch_dan_otorisasi(): void
    {
        [$faskes] = $this->seedPrediksi();
        $model = ModelPrediksi::first();
        Queue::fake();

        $gudang = $this->userWithRole('admin_gudang');
        $this->actingAs($gudang)->post("/prediksi/model/{$model->id}/train")->assertRedirect();
        Queue::assertPushed(TrainPrediksiModelJob::class);

        $puskes = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $this->actingAs($puskes)->post("/prediksi/model/{$model->id}/train")->assertForbidden();
    }

    public function test_train_semua_hanya_super_admin(): void
    {
        Queue::fake();
        $super = $this->userWithRole('super_admin');
        $this->actingAs($super)->post('/prediksi/model/train-semua')->assertRedirect();
        Queue::assertPushed(TrainPrediksiModelJob::class);

        $dinas = $this->userWithRole('admin_dinas');
        $this->actingAs($dinas)->post('/prediksi/model/train-semua')->assertForbidden();
    }
}
