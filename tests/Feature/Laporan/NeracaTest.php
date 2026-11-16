<?php

namespace Tests\Feature\Laporan;

use App\Models\FasilitasKesehatan;
use App\Models\NeracaTahunan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NeracaTest extends TestCase
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

    public function test_index_json_dan_scope(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/neraca')
            ->assertOk()
            ->assertSee('Neraca Tahunan', false);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/neraca?tahun=2099');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_store_mengenerate_dari_stok(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = \App\Models\Obat::factory()->create();
        \App\Models\StokFaskes::create(['fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'jumlah' => 40]);

        $this->actingAs($user)->post('/neraca', [
            'tahun' => (int) now()->format('Y'),
        ])->assertRedirect();

        $neraca = NeracaTahunan::first();
        $this->assertNotNull($neraca->nomor_neraca);
        $this->assertSame('draft', $neraca->status);
        $this->assertTrue($neraca->details()->where('obat_id', $obat->id)->exists());
    }

    public function test_finish_mengunci_dan_reopen_membuka(): void
    {
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);

        $neraca = NeracaTahunan::factory()->create(['fasilitas_id' => $faskes->id, 'status' => 'draft']);

        $this->actingAs($user)->patch("/neraca/{$neraca->id}/finish");
        $this->assertSame('selesai', $neraca->fresh()->status);

        $this->actingAs($user)->delete("/neraca/{$neraca->id}")->assertForbidden();

        $this->actingAs($user)->patch("/neraca/{$neraca->id}/reopen");
        $this->assertSame('draft', $neraca->fresh()->status);
    }

    public function test_cetak_route_terdaftar(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('neraca.cetak-pdf'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('neraca.cetak-xls'));
    }
}
