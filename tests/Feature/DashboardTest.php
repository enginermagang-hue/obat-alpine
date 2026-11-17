<?php

namespace Tests\Feature;

use App\Models\FasilitasKesehatan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_dashboard_dapat_diakses_user_login(): void
    {
        $user = User::factory()->create(['name' => 'Petugas Gudang']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Petugas Gudang')
            ->assertSee('Batch akan kedaluwarsa');
    }

    public function test_halaman_profil_dapat_diakses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profil')
            ->assertOk()
            ->assertSee('Tampilan & preferensi');
    }

    public function test_dashboard_dinas_menampilkan_status_puskesmas(): void
    {
        $dinas = User::factory()->create();
        $dinas->assignRole(Role::findByName('admin_dinas'));

        $this->actingAs($dinas)->get('/')
            ->assertOk()
            ->assertSee('Status puskesmas', false)
            ->assertSee('Tren distribusi', false);
    }

    public function test_dashboard_faskes_menampilkan_prediksi(): void
    {
        $faskes = FasilitasKesehatan::factory()->create();
        $user = User::factory()->create(['fasilitas_kesehatan_id' => $faskes->id]);
        $user->assignRole(Role::findByName('puskesmas'));

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Prediksi kebutuhan', false)
            ->assertSee('Pemakaian terbaru', false);
    }
}
