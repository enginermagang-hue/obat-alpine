<?php

namespace Tests\Feature\Stok;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\OpnameStok;
use App\Models\StokGudang;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpnameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super_admin'));

        return $user;
    }

    public function test_index_dan_create_dapat_diakses(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/opname')
            ->assertOk()
            ->assertSee('Stok Opname');

        $this->actingAs($this->superAdmin())
            ->get('/opname/create')
            ->assertOk()
            ->assertSee('Tambah baris');
    }

    public function test_pustu_ditolak(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('pustu'));

        $this->actingAs($user)->get('/opname')->assertForbidden();
    }

    public function test_store_menghitung_selisih_di_server(): void
    {
        $user = $this->superAdmin();
        $obat = Obat::factory()->create();
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => null,
            'jumlah' => 100,
            'status' => 'tersedia',
        ]);

        // Klien mengirim stok_sistem palsu — server harus mengabaikannya.
        $response = $this->actingAs($user)->post('/opname', [
            'tipe' => 'penyesuaian',
            'tanggal_opname' => now()->toDateString(),
            'status' => 'draft',
            'rows' => [[
                'obat_id' => $obat->id,
                'batch_id' => $batch->id,
                'stok_fisik' => 90,
            ]],
        ]);

        $response->assertRedirect();

        $opname = OpnameStok::first();
        $this->assertNotNull($opname);
        $detail = $opname->details()->first();
        $this->assertSame(100, $detail->stok_sistem);
        $this->assertSame(-10, $detail->selisih);
        $this->assertSame($batch->batch_number, $detail->batch_number);
    }

    public function test_selesai_mengubah_stok_dan_menulis_riwayat(): void
    {
        $user = $this->superAdmin();
        $obat = Obat::factory()->create();
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => null,
            'jumlah' => 100,
            'status' => 'tersedia',
        ]);
        StokGudang::create(['obat_id' => $obat->id, 'jumlah' => 100]);

        $this->actingAs($user)->post('/opname', [
            'tipe' => 'penyesuaian',
            'tanggal_opname' => now()->toDateString(),
            'status' => 'selesai',
            'rows' => [[
                'obat_id' => $obat->id,
                'batch_id' => $batch->id,
                'stok_fisik' => 90,
            ]],
        ])->assertRedirect();

        $opname = OpnameStok::first();
        $this->assertSame('selesai', $opname->status);
        $this->assertSame(90, $batch->fresh()->jumlah);
        $this->assertSame(90, StokGudang::where('obat_id', $obat->id)->first()->jumlah);
        $this->assertDatabaseHas('riwayat_stok', [
            'referensi_type' => OpnameStok::class,
            'referensi_id' => $opname->id,
            'tipe' => 'opname',
        ]);
    }

    public function test_opname_selesai_terkunci_dan_bisa_dibuka_kembali(): void
    {
        $user = $this->superAdmin();
        $obat = Obat::factory()->create();
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => null,
            'jumlah' => 100,
            'status' => 'tersedia',
        ]);
        StokGudang::create(['obat_id' => $obat->id, 'jumlah' => 100]);

        $this->actingAs($user)->post('/opname', [
            'tipe' => 'penyesuaian',
            'tanggal_opname' => now()->toDateString(),
            'status' => 'selesai',
            'rows' => [[
                'obat_id' => $obat->id,
                'batch_id' => $batch->id,
                'stok_fisik' => 80,
            ]],
        ]);

        $opname = OpnameStok::first();
        $this->assertSame(80, StokGudang::where('obat_id', $obat->id)->first()->jumlah);

        // Edit langsung ditolak
        $this->actingAs($user)
            ->put("/opname/{$opname->id}", [
                'tipe' => 'penyesuaian',
                'tanggal_opname' => now()->toDateString(),
                'status' => 'draft',
                'rows' => [[
                    'obat_id' => $obat->id,
                    'batch_id' => $batch->id,
                    'stok_fisik' => 70,
                ]],
            ])
            ->assertRedirect("/opname/{$opname->id}")
            ->assertSessionHas('error');

        // Buka kembali → stok pulih, status draft
        $this->actingAs($user)
            ->patch("/opname/{$opname->id}/reopen")
            ->assertRedirect("/opname/{$opname->id}")
            ->assertSessionHas('success');

        $this->assertSame('draft', $opname->fresh()->status);
        $this->assertSame(100, StokGudang::where('obat_id', $obat->id)->first()->jumlah);

        // Hapus draft boleh
        $this->actingAs($user)
            ->delete("/opname/{$opname->id}")
            ->assertRedirect('/opname');

        $this->assertDatabaseMissing('opname_stok', ['id' => $opname->id]);
    }

    public function test_hapus_ditolak_bila_bukan_draft(): void
    {
        $user = $this->superAdmin();
        $opname = OpnameStok::create([
            'tipe' => 'penyesuaian',
            'tanggal_opname' => now()->toDateString(),
            'status' => 'proses',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete("/opname/{$opname->id}")
            ->assertRedirect("/opname/{$opname->id}")
            ->assertSessionHas('error');
    }

    public function test_stok_awal_membuat_batch_baru(): void
    {
        $user = $this->superAdmin();
        $obat = Obat::factory()->create();

        $this->actingAs($user)->post('/opname', [
            'tipe' => 'stok_awal',
            'tanggal_opname' => now()->toDateString(),
            'status' => 'selesai',
            'rows' => [[
                'obat_id' => $obat->id,
                'batch_number' => 'BTH-AWAL-1',
                'tanggal_expired' => now()->addYear()->toDateString(),
                'stok_fisik' => 50,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('batch_stok', [
            'obat_id' => $obat->id,
            'batch_number' => 'BTH-AWAL-1',
            'jumlah' => 50,
        ]);
    }

    public function test_lookup_menolak_faskes_luar_wewenang(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create();
        $user = User::factory()->create(['fasilitas_kesehatan_id' => $faskesA->id]);
        $user->assignRole(Role::findByName('puskesmas'));

        $this->actingAs($user)
            ->getJson("/api/batch?obat_id={$obat->id}&fasilitas_id={$faskesB->id}")
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/obat/search?q=test')
            ->assertOk();
    }
}
