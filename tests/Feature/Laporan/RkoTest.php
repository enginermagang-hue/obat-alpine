<?php

namespace Tests\Feature\Laporan;

use App\Models\FasilitasKesehatan;
use App\Models\LaporanRko;
use App\Models\Obat;
use App\Models\PengaturanLaporan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RkoTest extends TestCase
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

    private function bukaAkses(int $faskesId, int $tahun): void
    {
        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => $faskesId, 'grup' => 'rko', 'key' => 'akses_dibuka'],
            ['value' => '1'],
        );
        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => $faskesId, 'grup' => 'rko', 'key' => 'periode_tahun'],
            ['value' => (string) $tahun],
        );
    }

    public function test_index_json_dan_scope(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/rko')
            ->assertOk()
            ->assertSee('RKO', false);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/rko?status=draft');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_create_ditolak_bila_akses_belum_dibuka(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);

        PengaturanLaporan::where('grup', 'rko')->where('fasilitas_id', $faskes->id)->delete();

        $this->actingAs($user)->get('/rko/create')->assertForbidden();
    }

    public function test_store_menggunakan_periode_bukaan(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $tahun = (int) now()->addYear()->format('Y');
        $this->bukaAkses($faskes->id, $tahun);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();

        $this->actingAs($user)->post('/rko', [
            'tanggal_pembuatan' => now()->toDateString(),
            'rows' => [[
                'obat_id' => $obat->id,
                'pemakaian_tahun_sebelumnya' => 120,
                'rata_rata_pemakaian_bulanan' => 10,
                'stok_akhir' => 20,
                'usulan' => 100,
                'harga_perkiraan' => 5000,
                'buffer_stock_persen' => 10,
            ]],
        ])->assertRedirect();

        $rko = LaporanRko::first();
        $this->assertSame($tahun, $rko->periode_tahun);
        $this->assertSame('draft', $rko->status);

        $detail = $rko->details()->first();
        $this->assertSame(10, (int) $detail->buffer_stok_qty);
        $this->assertSame(110, $detail->total_kebutuhan);
        $this->assertEquals(500000, (float) $detail->total_harga);
        $this->assertEquals(500000, (float) $rko->fresh()->total_anggaran);
    }

    public function test_unik_satu_rko_per_tahun(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $tahun = (int) now()->addYear()->format('Y');
        $this->bukaAkses($faskes->id, $tahun);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();

        $rows = [['obat_id' => $obat->id, 'usulan' => 10]];

        $this->actingAs($user)->post('/rko', [
            'tanggal_pembuatan' => now()->toDateString(),
            'rows' => $rows,
        ])->assertRedirect();

        $this->assertSame(1, LaporanRko::where('fasilitas_id', $faskes->id)->where('periode_tahun', $tahun)->count());

        // RKO kedua untuk periode sama ditolak di level policy (403).
        $this->actingAs($user)->post('/rko', [
            'tanggal_pembuatan' => now()->toDateString(),
            'rows' => $rows,
        ])->assertForbidden();

        $this->assertSame(1, LaporanRko::where('fasilitas_id', $faskes->id)->where('periode_tahun', $tahun)->count());
    }

    public function test_ajukan_lalu_disetujui_dinas(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $this->bukaAkses($faskes->id, (int) now()->addYear()->format('Y'));
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $dinas = $this->userWithRole('admin_dinas');

        $rko = LaporanRko::factory()->create(['fasilitas_id' => $faskes->id, 'status' => 'draft']);

        $this->actingAs($user)->patch("/rko/{$rko->id}/submit");
        $this->assertSame('diajukan', $rko->fresh()->status);

        $this->actingAs($dinas)->patch("/rko/{$rko->id}/approve");
        $rko->refresh();
        $this->assertSame('disetujui', $rko->status);
        $this->assertSame($dinas->id, $rko->disetujui_oleh);
    }

    public function test_cetak_route_terdaftar(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('rko.cetak-pdf'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('rko.cetak-xls'));
    }
}
