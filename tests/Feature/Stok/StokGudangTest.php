<?php

namespace Tests\Feature\Stok;

use App\Models\StokGudang;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StokGudangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    public function test_index_dapat_diakses_dan_menampilkan_status(): void
    {
        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get('/stok-gudang')
            ->assertOk()
            ->assertSee('Stok Gudang');
    }

    public function test_index_ditolak_untuk_puskesmas(): void
    {
        $this->actingAs($this->userWithRole('puskesmas'))
            ->get('/stok-gudang')
            ->assertForbidden();
    }

    public function test_filter_status_habis(): void
    {
        $user = $this->userWithRole('admin_gudang');
        StokGudang::create(['obat_id' => \App\Models\Obat::factory()->create(['nama_obat' => 'Obat Habis Unik'])->id, 'jumlah' => 0, 'stok_minimum' => 10]);
        StokGudang::create(['obat_id' => \App\Models\Obat::factory()->create(['nama_obat' => 'Obat Ada Unik'])->id, 'jumlah' => 100, 'stok_minimum' => 10]);

        $response = $this->actingAs($user)->get('/stok-gudang?status_stok=habis');

        $response->assertOk()->assertSee('Obat Habis Unik');
        $response->assertDontSee('Obat Ada Unik', false);
    }

    public function test_show_menampilkan_batch(): void
    {
        $obat = \App\Models\Obat::factory()->create();
        \App\Models\BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => null, 'batch_number' => 'BTH-SHOW-1']);

        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get("/stok-gudang/{$obat->id}")
            ->assertOk()
            ->assertSee('BTH-SHOW-1');
    }

    public function test_recalculate_menghitung_minimum(): void
    {
        $this->actingAs($this->userWithRole('admin_gudang'))
            ->post('/stok-gudang/recalculate')
            ->assertRedirect('/stok-gudang')
            ->assertSessionHas('success');
    }
}
