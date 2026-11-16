<?php

namespace Tests\Feature\Laporan;

use App\Models\FasilitasKesehatan;
use App\Models\SumberDana;
use App\Models\SumberDanaPenggunaan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlokasiDanaTest extends TestCase
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

    public function test_index_dan_json_untuk_dinas(): void
    {
        $dinas = $this->userWithRole('admin_dinas');

        $this->actingAs($dinas)->get('/alokasi-dana')->assertOk()->assertSee('Alokasi Dana', false);

        SumberDana::factory()->create(['kode' => 'DN-0001', 'tahun' => 2026]);

        $response = $this->actingAs($dinas)->getJson('/alokasi-dana?tahun=2026');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertArrayHasKey('sisa', $response->json('data.0'));
        $this->assertArrayHasKey('persen', $response->json('data.0'));
    }

    public function test_statistik_menghitung_sisa_dan_persen(): void
    {
        $dinas = $this->userWithRole('admin_dinas');
        $dana = SumberDana::factory()->create(['total_anggaran' => 1000000, 'tahun' => 2026]);

        SumberDanaPenggunaan::create([
            'sumber_dana_id' => $dana->id,
            'tipe' => 'realisasi',
            'jumlah_obat' => 30,
            'total_biaya' => 300000,
            'tanggal' => '2026-03-10',
        ]);
        SumberDanaPenggunaan::create([
            'sumber_dana_id' => $dana->id,
            'tipe' => 'alokasi',
            'jumlah_obat' => 20,
            'total_biaya' => 200000,
            'tanggal' => '2026-04-01',
        ]);

        $this->actingAs($dinas)->get('/alokasi-dana?tahun=2026')
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['anggaran'] == 1000000
                && $stats['realisasi'] == 300000
                && $stats['sisa'] == 700000
                && $stats['persen'] == 30.0
                && $stats['alokasi'] == 200000);
    }

    public function test_chart_dataset_tersedia(): void
    {
        $dinas = $this->userWithRole('admin_dinas');
        SumberDana::factory()->create(['kode' => 'DN-0002', 'tahun' => 2026]);

        $this->actingAs($dinas)->get('/alokasi-dana?tahun=2026')
            ->assertOk()
            ->assertViewHas('charts', fn ($charts) => isset($charts['bar'], $charts['doughnut'], $charts['tren'])
                && $charts['bar']['type'] === 'bar'
                && $charts['doughnut']['type'] === 'doughnut'
                && $charts['tren']['type'] === 'line'
                && count($charts['tren']['data']['datasets'][0]['data']) === 12);
    }

    public function test_tanpa_permission_ditolak(): void
    {
        $user = User::factory()->create(['fasilitas_kesehatan_id' => FasilitasKesehatan::factory()->create()->id]);
        $user->assignRole(Role::findByName('puskesmas'));

        $this->actingAs($user)->get('/alokasi-dana')->assertForbidden();
    }
}
