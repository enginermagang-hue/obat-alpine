<?php

namespace Tests\Feature\Transaksi;

use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PermintaanObat;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermintaanTest extends TestCase
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

    private function rows(Obat $obat, int $jumlah = 100): array
    {
        return [[
            'obat_id' => $obat->id,
            'jumlah_diminta' => $jumlah,
        ]];
    }

    public function test_index_json_dan_scope_dinas(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/permintaan')
            ->assertOk()
            ->assertSee('Permintaan', false);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/permintaan?status=draft');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_puskesmas_buat_permintaan_otomatis_dinas(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);

        $this->actingAs($user)->post('/permintaan', [
            'tanggal_permintaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 100),
        ])->assertRedirect();

        $permintaan = PermintaanObat::first();
        $this->assertSame('puskesmas_ke_dinas', $permintaan->tipe_permintaan);
        $this->assertSame($puskesmas->id, $permintaan->fasilitas_pengirim_id);
        $this->assertNull($permintaan->fasilitas_tujuan_id);
        $this->assertSame('draft', $permintaan->status);
    }

    public function test_pustu_buat_permintaan_ke_induk(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $pustu = FasilitasKesehatan::factory()->pustu($induk->id)->create();
        $obat = Obat::factory()->create();
        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);

        $this->actingAs($user)->post('/permintaan', [
            'tanggal_permintaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 50),
        ])->assertRedirect();

        $permintaan = PermintaanObat::first();
        $this->assertSame('pustu_ke_puskesmas', $permintaan->tipe_permintaan);
        $this->assertSame($induk->id, $permintaan->fasilitas_tujuan_id);
    }

    public function test_super_admin_tidak_bisa_create(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/permintaan/create')
            ->assertForbidden();
    }

    public function test_ajukan_lalu_setujui_dengan_jumlah_berbeda(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $pemohon = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);

        $this->actingAs($pemohon)->post('/permintaan', [
            'tanggal_permintaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 100),
        ]);

        $permintaan = PermintaanObat::first();

        $this->actingAs($pemohon)->patch("/permintaan/{$permintaan->id}/submit");

        $this->assertSame('menunggu_persetujuan', $permintaan->fresh()->status);

        $dinas = $this->userWithRole('admin_dinas');

        $detailId = $permintaan->details()->first()->id;

        $this->actingAs($dinas)->patch("/permintaan/{$permintaan->id}/approve", [
            'jumlah_disetujui' => [$detailId => 80],
        ])->assertRedirect("/permintaan/{$permintaan->id}");

        $permintaan->refresh();
        $this->assertSame('disetujui', $permintaan->status);
        $this->assertSame(80, $permintaan->details()->first()->jumlah_disetujui);
        $this->assertSame($dinas->id, $permintaan->disetujui_oleh);
    }

    public function test_tolak_butuh_alasan(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $pemohon = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);

        $this->actingAs($pemohon)->post('/permintaan', [
            'tanggal_permintaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 100),
        ]);

        $permintaan = PermintaanObat::first();
        $this->actingAs($pemohon)->patch("/permintaan/{$permintaan->id}/submit");

        $dinas = $this->userWithRole('admin_dinas');

        $this->actingAs($dinas)
            ->patch("/permintaan/{$permintaan->id}/reject", [])
            ->assertSessionHasErrors('alasan_penolakan');

        $this->actingAs($dinas)
            ->patch("/permintaan/{$permintaan->id}/reject", ['alasan_penolakan' => 'Stok gudang kosong'])
            ->assertRedirect("/permintaan/{$permintaan->id}");

        $this->assertSame('ditolak', $permintaan->fresh()->status);
    }

    public function test_puskesmas_tidak_melihat_permintaan_faskes_lain(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $faskesB = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        PermintaanObat::factory()->create([
            'fasilitas_pengirim_id' => $faskesB->id,
            'tipe_permintaan' => 'puskesmas_ke_dinas',
            'status' => 'draft',
        ])->details()->create(['obat_id' => $obat->id, 'jumlah_diminta' => 10]);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/permintaan');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }
}
