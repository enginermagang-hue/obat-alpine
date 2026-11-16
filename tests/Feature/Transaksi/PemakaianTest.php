<?php

namespace Tests\Feature\Transaksi;

use App\Models\BatchStok;
use App\Models\DetailPemakaianObat;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\StokFaskes;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PemakaianTest extends TestCase
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

    private function stokObat(Obat $obat, int $faskesId, int $jumlah = 100): void
    {
        BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => $faskesId,
            'jumlah' => $jumlah,
            'status' => 'tersedia',
            'tanggal_expired' => now()->addYear()->toDateString(),
        ]);
        StokFaskes::create(['fasilitas_id' => $faskesId, 'obat_id' => $obat->id, 'jumlah' => $jumlah]);
    }

    private function rows(Obat $obat, int $jumlah = 30): array
    {
        return [[
            'obat_id' => $obat->id,
            'jumlah' => $jumlah,
            'dosis' => '3x1',
        ]];
    }

    public function test_index_json_dan_scope(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/pemakaian')
            ->assertOk()
            ->assertSee('Pemakaian', false);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/pemakaian?layanan=apotek');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_store_mengalokasikan_batch_dan_menurunkan_stok(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create(['metode_stok' => 'fefo']);
        $this->stokObat($obat, $faskes->id, 100);

        $this->actingAs($user)->post('/pemakaian', [
            'tanggal_pemakaian' => now()->toDateString(),
            'jenis_pelayanan' => 'rawat_jalan',
            'rows' => $this->rows($obat, 30),
        ])->assertRedirect();

        $pemakaian = PemakaianObat::first();
        $this->assertNotNull($pemakaian->nomor_pemakaian);
        $this->assertSame(30, $pemakaian->details()->first()->jumlah);
        $this->assertNotNull($pemakaian->details()->first()->batch_id);
        $this->assertSame(70, StokFaskes::where('fasilitas_id', $faskes->id)->where('obat_id', $obat->id)->first()->jumlah);
        $this->assertDatabaseHas('riwayat_stok', [
            'referensi_type' => DetailPemakaianObat::class,
            'tipe' => 'keluar',
        ]);
    }

    public function test_store_ditolak_bila_stok_kurang_dan_tidak_ada_sisa(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();
        $this->stokObat($obat, $faskes->id, 10);

        $this->actingAs($user)->post('/pemakaian', [
            'tanggal_pemakaian' => now()->toDateString(),
            'jenis_pelayanan' => 'apotek',
            'rows' => $this->rows($obat, 100),
        ])->assertStatus(422);

        $this->assertSame(0, PemakaianObat::count());
    }

    public function test_update_membalik_lalu_menerapkan_ulang(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();
        $this->stokObat($obat, $faskes->id, 100);

        $this->actingAs($user)->post('/pemakaian', [
            'tanggal_pemakaian' => now()->toDateString(),
            'jenis_pelayanan' => 'rawat_jalan',
            'rows' => $this->rows($obat, 30),
        ]);

        $pemakaian = PemakaianObat::first();

        $this->actingAs($user)->put("/pemakaian/{$pemakaian->id}", [
            'tanggal_pemakaian' => now()->toDateString(),
            'jenis_pelayanan' => 'rawat_jalan',
            'rows' => $this->rows($obat, 20),
        ])->assertRedirect("/pemakaian/{$pemakaian->id}");

        // 100 - 30 + 30 - 20 = 80.
        $this->assertSame(80, StokFaskes::where('fasilitas_id', $faskes->id)->where('obat_id', $obat->id)->first()->jumlah);
        $this->assertSame(20, $pemakaian->fresh()->details()->sum('jumlah'));
    }

    public function test_edit_ditolak_bila_bukan_hari_ini(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();
        $this->stokObat($obat, $faskes->id, 100);

        $lama = PemakaianObat::factory()->create([
            'fasilitas_id' => $faskes->id,
            'tanggal_pemakaian' => now()->subDays(2)->toDateString(),
            'jenis_pelayanan' => 'rawat_jalan',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get("/pemakaian/{$lama->id}/edit")->assertForbidden();
        $this->actingAs($user)->delete("/pemakaian/{$lama->id}")->assertForbidden();
    }

    public function test_admin_tidak_bisa_create(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/pemakaian/create')
            ->assertForbidden();
    }
}
