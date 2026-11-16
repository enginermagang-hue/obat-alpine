<?php

namespace Tests\Feature\Stok;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\StokFaskes;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StokFaskesTest extends TestCase
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

    public function test_index_dapat_diakses_dan_menampilkan_data(): void
    {
        $this->actingAs($this->userWithRole('puskesmas'))
            ->get('/stok-faskes')
            ->assertOk()
            ->assertSee('Stok Faskes');
    }

    public function test_user_faskes_hanya_melihat_stok_sendiri(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        $obatA = Obat::factory()->create(['nama_obat' => 'Obat Milik Alfa Unik']);
        $obatB = Obat::factory()->create(['nama_obat' => 'Obat Milik Beta Unik']);
        StokFaskes::create(['fasilitas_id' => $faskesA->id, 'obat_id' => $obatA->id, 'jumlah' => 10]);
        StokFaskes::create(['fasilitas_id' => $faskesB->id, 'obat_id' => $obatB->id, 'jumlah' => 20]);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->get('/stok-faskes');

        $response->assertOk()->assertSee('Obat Milik Alfa Unik');
        $response->assertDontSee('Obat Milik Beta Unik', false);
    }

    public function test_show_ditolak_untuk_faskes_lain(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create();
        StokFaskes::create(['fasilitas_id' => $faskesB->id, 'obat_id' => $obat->id, 'jumlah' => 5]);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $this->actingAs($user)
            ->get("/stok-faskes/{$faskesB->id}/{$obat->id}")
            ->assertForbidden();
    }

    public function test_show_dapat_diakses_dan_filter_expiry(): void
    {
        $faskes = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create();
        StokFaskes::create(['fasilitas_id' => $faskes->id, 'obat_id' => $obat->id, 'jumlah' => 15]);
        BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => $faskes->id,
            'batch_number' => 'BTH-FSK-1',
            'tanggal_expired' => now()->addDays(10)->toDateString(),
        ]);

        $user = $this->userWithRole('admin_dinas');

        $response = $this->actingAs($user)->get("/stok-faskes/{$faskes->id}/{$obat->id}?expiry=d30");

        $response->assertOk()->assertSee('BTH-FSK-1');
    }

    public function test_recalculate_menghitung_minimum(): void
    {
        $this->actingAs($this->userWithRole('admin_dinas'))
            ->post('/stok-faskes/recalculate')
            ->assertRedirect('/stok-faskes')
            ->assertSessionHas('success');
    }
}
