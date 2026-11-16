<?php

namespace Tests\Feature\Master;

use App\Models\FasilitasKesehatan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FasilitasTest extends TestCase
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

    public function test_index_dapat_diakses_super_admin_dan_dinas(): void
    {
        FasilitasKesehatan::factory()->create(['nama' => 'Puskesmas Test']);

        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/fasilitas')
            ->assertOk()
            ->assertSee('Fasilitas Kesehatan', false);

        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/fasilitas')
            ->assertOk()
            ->assertDontSee('Tambah Faskes', false);
    }

    public function test_tabel_json_filter_tipe(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['nama' => 'Puskesmas Induk Json', 'tipe' => 'puskesmas']);
        FasilitasKesehatan::factory()->create(['nama' => 'Puskesmas Alfa Json', 'tipe' => 'puskesmas']);
        FasilitasKesehatan::factory()->pustu($induk->id)->create(['nama' => 'Pustu Beta Json']);

        $response = $this->actingAs($this->userWithRole('super_admin'))->getJson('/fasilitas?tipe=pustu');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Pustu Beta Json', $response->json('data.0.nama'));
    }

    public function test_index_ditolak_untuk_puskesmas(): void
    {
        $this->actingAs($this->userWithRole('puskesmas'))
            ->get('/fasilitas')
            ->assertForbidden();
    }

    public function test_store_puskesmas_tanpa_induk(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->post('/fasilitas', [
                'kode_faskes' => 'FSK-TEST1',
                'nama' => 'Puskesmas Baru',
                'tipe' => 'puskesmas',
                'alamat' => 'Jl. Test No. 1',
                'status' => 'aktif',
            ])
            ->assertRedirect('/fasilitas')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fasilitas_kesehatan', ['kode_faskes' => 'FSK-TEST1']);
    }

    public function test_store_pustu_wajib_induk_puskesmas(): void
    {
        $user = $this->userWithRole('super_admin');
        $pustuLain = FasilitasKesehatan::factory()->pustu()->create();

        // Tanpa induk → ditolak
        $this->actingAs($user)
            ->post('/fasilitas', [
                'kode_faskes' => 'PST-TEST1',
                'nama' => 'Pustu Tanpa Induk',
                'tipe' => 'pustu',
                'alamat' => 'Jl. Test No. 2',
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('puskesmas_induk_id');

        // Induk bertipe pustu → ditolak
        $this->actingAs($user)
            ->post('/fasilitas', [
                'kode_faskes' => 'PST-TEST2',
                'nama' => 'Pustu Salah Induk',
                'tipe' => 'pustu',
                'puskesmas_induk_id' => $pustuLain->id,
                'alamat' => 'Jl. Test No. 3',
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('puskesmas_induk_id');
    }

    public function test_deactivate_dan_destroy_guard(): void
    {
        $user = $this->userWithRole('super_admin');
        $faskes = FasilitasKesehatan::factory()->create(['status' => 'aktif']);

        $this->actingAs($user)
            ->patch("/fasilitas/{$faskes->id}/deactivate")
            ->assertRedirect('/fasilitas');

        $this->assertSame('nonaktif', $faskes->fresh()->status);

        // Faskes dipakai user → hapus ditolak
        User::factory()->create(['fasilitas_kesehatan_id' => $faskes->id]);

        $this->actingAs($user)
            ->delete("/fasilitas/{$faskes->id}")
            ->assertRedirect('/fasilitas')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('fasilitas_kesehatan', ['id' => $faskes->id]);
    }
}
