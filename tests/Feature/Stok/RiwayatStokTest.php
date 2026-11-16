<?php

namespace Tests\Feature\Stok;

use App\Models\FasilitasKesehatan;
use App\Models\RiwayatStok;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiwayatStokTest extends TestCase
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

    public function test_index_dapat_diakses_dan_filter_tipe(): void
    {
        RiwayatStok::factory()->create(['tipe' => 'masuk', 'keterangan' => 'Mutasi masuk unik']);
        RiwayatStok::factory()->create(['tipe' => 'keluar', 'keterangan' => 'Mutasi keluar unik']);

        $response = $this->actingAs($this->userWithRole('admin_gudang'))->getJson('/riwayat-stok?tipe=keluar');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Mutasi keluar unik', $response->json('data.0.keterangan'));
    }

    public function test_filter_rentang_tanggal(): void
    {
        RiwayatStok::factory()->create(['tanggal' => now()->subDays(2)->toDateString(), 'keterangan' => 'Riwayat lama unik']);
        RiwayatStok::factory()->create(['tanggal' => now()->toDateString(), 'keterangan' => 'Riwayat hari ini unik']);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))
            ->getJson('/riwayat-stok?dari='.now()->toDateString().'&sampai='.now()->toDateString());

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Riwayat hari ini unik', $response->json('data.0.keterangan'));
    }

    public function test_user_faskes_hanya_melihat_riwayat_sendiri(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        RiwayatStok::factory()->create(['fasilitas_id' => $faskesA->id, 'keterangan' => 'Riwayat Alfa unik']);
        RiwayatStok::factory()->create(['fasilitas_id' => $faskesB->id, 'keterangan' => 'Riwayat Beta unik']);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/riwayat-stok');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Riwayat Alfa unik', $response->json('data.0.keterangan'));
    }
}
