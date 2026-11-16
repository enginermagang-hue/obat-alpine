<?php

namespace Tests\Feature\Transaksi;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\InspeksiRetur;
use App\Models\Obat;
use App\Models\ReturObat;
use App\Models\StokFaskes;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspeksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function returSiapInspeksi(): ReturObat
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => $puskesmas->id,
            'jumlah' => 100,
            'status' => 'tersedia',
        ]);
        StokFaskes::create(['fasilitas_id' => $puskesmas->id, 'obat_id' => $obat->id, 'jumlah' => 100]);

        return tap(ReturObat::factory()->create([
            'fasilitas_pengirim_id' => $puskesmas->id,
            'tipe_retur' => 'puskesmas_ke_gudang',
            'status' => 'diterima',
        ]), function (ReturObat $retur) use ($obat, $batch): void {
            $retur->details()->create([
                'obat_id' => $obat->id,
                'batch_id' => $batch->id,
                'jumlah_retur' => 10,
            ]);
        });
    }

    private function payload(ReturObat $retur, array $over = []): array
    {
        $detail = $retur->details()->first();

        return array_merge([
            'retur_id' => $retur->id,
            'detail_retur_id' => $detail->id,
            'batch_id' => $detail->batch_id,
            'hasil_inspeksi' => 'layak',
            'tindakan' => 'didistribusi_kembali',
            'tanggal_inspeksi' => now()->toDateString(),
        ], $over);
    }

    public function test_index_dan_create_dapat_diakses_gudang(): void
    {
        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get('/inspeksi-retur')
            ->assertOk()
            ->assertSee('Inspeksi Retur', false);

        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get('/inspeksi-retur/create')
            ->assertOk();
    }

    public function test_pustu_ditolak_dan_dinas_tidak_bisa_create(): void
    {
        $pustu = FasilitasKesehatan::factory()->pustu()->create();
        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);

        $this->actingAs($user)->get('/inspeksi-retur')->assertForbidden();

        $this->actingAs($this->userWithRole('admin_dinas'))->get('/inspeksi-retur/create')->assertForbidden();
    }

    public function test_store_mencatat_tanpa_mengubah_stok(): void
    {
        $gudang = $this->userWithRole('admin_gudang');
        $retur = $this->returSiapInspeksi();
        $batch = $retur->details()->first()->batch;

        $this->actingAs($gudang)
            ->post('/inspeksi-retur', $this->payload($retur))
            ->assertRedirect('/inspeksi-retur');

        $this->assertSame(1, InspeksiRetur::count());
        // Stok tidak berubah.
        $this->assertSame(100, $batch->fresh()->jumlah);
    }

    public function test_duplikat_per_detail_ditolak(): void
    {
        $gudang = $this->userWithRole('admin_gudang');
        $retur = $this->returSiapInspeksi();

        $this->actingAs($gudang)->post('/inspeksi-retur', $this->payload($retur))->assertRedirect();

        $this->actingAs($gudang)->post('/inspeksi-retur', $this->payload($retur))->assertStatus(422);
        $this->assertSame(1, InspeksiRetur::count());
    }

    public function test_puskesmas_bisa_inspeksi_sebagai_penerima(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $pustu = FasilitasKesehatan::factory()->pustu($induk->id)->create();
        $obat = Obat::factory()->create();
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id, 'fasilitas_id' => $pustu->id, 'jumlah' => 50, 'status' => 'tersedia',
        ]);

        $retur = ReturObat::factory()->create([
            'fasilitas_pengirim_id' => $pustu->id,
            'fasilitas_penerima_id' => $induk->id,
            'tipe_retur' => 'pustu_ke_puskesmas',
            'status' => 'diterima',
        ]);
        $retur->details()->create(['obat_id' => $obat->id, 'batch_id' => $batch->id, 'jumlah_retur' => 5]);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $induk->id]);

        $this->actingAs($user)->post('/inspeksi-retur', $this->payload($retur))
            ->assertRedirect('/inspeksi-retur');

        $this->assertSame(1, InspeksiRetur::count());
    }
}
