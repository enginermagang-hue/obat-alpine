<?php

namespace Tests\Feature\Laporan;

use App\Models\FasilitasKesehatan;
use App\Models\LaporanLplpo;
use App\Models\Obat;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LplpoTest extends TestCase
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

    public function test_index_json_dan_scope_faskes(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $faskesB = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        LaporanLplpo::factory()->create(['fasilitas_id' => $faskesB->id, 'periode_bulan' => 1, 'periode_tahun' => 2026]);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/lplpo');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_show_menampilkan_data_asli_bukan_kosong(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $laporan = LaporanLplpo::factory()->create(['fasilitas_id' => $faskes->id, 'status' => 'draft']);

        $this->actingAs($user)
            ->get("/lplpo/{$laporan->id}")
            ->assertOk()
            ->assertSee($laporan->nomor_laporan)
            ->assertSee($faskes->nama);
    }

    public function test_store_menggenerate_detail_dari_riwayat(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();

        \App\Models\RiwayatStok::factory()->create([
            'fasilitas_id' => $faskes->id,
            'obat_id' => $obat->id,
            'tipe' => 'masuk',
            'jumlah' => 50,
            'stok_sebelum' => 0,
            'stok_sesudah' => 50,
            'tanggal' => now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAs($user)->post('/lplpo', [
            'periode_bulan' => (int) now()->format('n'),
            'periode_tahun' => (int) now()->format('Y'),
            'tanggal_pembuatan' => now()->toDateString(),
        ])->assertRedirect();

        $laporan = LaporanLplpo::first();
        $this->assertNotNull($laporan->nomor_laporan);
        $this->assertTrue($laporan->details()->where('obat_id', $obat->id)->exists());
    }

    public function test_ajukan_lalu_verifikasi(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $dinas = $this->userWithRole('admin_dinas');

        $laporan = LaporanLplpo::factory()->create(['fasilitas_id' => $faskes->id, 'status' => 'draft']);

        $this->actingAs($user)->patch("/lplpo/{$laporan->id}/submit");
        $this->assertSame('menunggu_verifikasi', $laporan->fresh()->status_verifikasi);

        $this->actingAs($dinas)->patch("/lplpo/{$laporan->id}/verify");

        $laporan->refresh();
        $this->assertSame('selesai', $laporan->status);
        $this->assertSame('sudah_diverifikasi', $laporan->status_verifikasi);
    }

    public function test_buat_revisi_menduplikasi_draft(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();

        $laporan = LaporanLplpo::factory()->create(['fasilitas_id' => $faskes->id, 'status' => 'draft']);
        $laporan->details()->create([
            'obat_id' => $obat->id,
            'stok_awal' => 10, 'jumlah_masuk' => 5, 'jumlah_keluar' => 3,
            'sisa_stok' => 12, 'permintaan_selanjutnya' => 20,
        ]);

        $this->actingAs($user)->post("/lplpo/{$laporan->id}/revise")
            ->assertRedirect();

        $this->assertSame(2, LaporanLplpo::count());
        $revisi = LaporanLplpo::where('parent_lplpo_id', $laporan->id)->first();
        $this->assertNotNull($revisi);
        $this->assertSame('draft', $revisi->status);
        $this->assertSame(1, $revisi->details()->count());
    }

    public function test_cetak_route_terdaftar(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('lplpo.cetak'));
    }
}
