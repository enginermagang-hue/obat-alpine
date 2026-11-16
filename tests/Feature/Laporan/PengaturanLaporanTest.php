<?php

namespace Tests\Feature\Laporan;

use App\Models\FasilitasKesehatan;
use App\Models\PengaturanLaporan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PengaturanLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function dinas(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_dinas'));

        return $user;
    }

    public function test_dinas_bisa_membuka_panel(): void
    {
        $this->actingAs($this->dinas())
            ->get('/pengaturan-laporan')
            ->assertOk()
            ->assertSee('Pengaturan Laporan', false);
    }

    public function test_tanpa_permission_ditolak(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = User::factory()->create(['fasilitas_kesehatan_id' => $faskes->id]);
        $user->assignRole(Role::findByName('puskesmas'));

        $this->actingAs($user)->get('/pengaturan-laporan')->assertForbidden();
        $this->actingAs($user)->patch('/pengaturan-laporan/rko', ['periode_tahun' => 2027])->assertForbidden();
    }

    public function test_simpan_global_membuka_akses_rko(): void
    {
        $tahun = (int) now()->addYear()->format('Y');

        $this->actingAs($this->dinas())->patch('/pengaturan-laporan/rko', [
            'akses_dibuka' => '1',
            'periode_tahun' => $tahun,
            'deadline' => now()->addMonth()->format('Y-m-d'),
        ])->assertRedirect('/pengaturan-laporan');

        $this->assertSame('1', PengaturanLaporan::get('rko', 'akses_dibuka'));
        $this->assertSame((string) $tahun, PengaturanLaporan::get('rko', 'periode_tahun'));
    }

    public function test_override_faskes_dan_kembali_global(): void
    {
        $dinas = $this->dinas();
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);

        $this->actingAs($dinas)->patch("/pengaturan-laporan/rko/faskes/{$faskes->id}", [
            'akses_dibuka' => '1',
            'periode_tahun' => 2028,
        ])->assertRedirect();

        $this->assertSame('1', PengaturanLaporan::get('rko', 'akses_dibuka', $faskes->id));
        $this->assertSame('2028', PengaturanLaporan::get('rko', 'periode_tahun', $faskes->id));

        $this->actingAs($dinas)->patch("/pengaturan-laporan/rko/faskes/{$faskes->id}", [
            'ikut_global' => '1',
            'periode_tahun' => 2028,
        ])->assertRedirect();

        $this->assertSame(0, PengaturanLaporan::where('fasilitas_id', $faskes->id)->where('grup', 'rko')->count());
    }
}
