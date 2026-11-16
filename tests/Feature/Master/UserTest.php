<?php

namespace Tests\Feature\Master;

use App\Models\PenerimaanStok;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super_admin'));

        return $user;
    }

    public function test_index_dapat_diakses_dan_filter_role(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'Petugas Puskesmas'])->assignRole('puskesmas');
        User::factory()->create(['name' => 'Staf Gudang'])->assignRole('admin_gudang');

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertSee('Pengguna &amp; Role', false);

        $response = $this->actingAs($admin)->getJson('/users?role=puskesmas');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Petugas Puskesmas', $response->json('data.0.name'));
    }

    public function test_index_ditolak_untuk_admin_gudang(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_gudang'));

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_admin_dinas_hanya_baca(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_dinas'));

        $this->actingAs($user)
            ->get('/users')
            ->assertOk()
            ->assertDontSee('Tambah Pengguna', false);
    }

    public function test_store_membuat_user_dengan_role(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'User Baru',
                'email' => 'baru@instansi.go.id',
                'password' => 'password-123',
                'password_confirmation' => 'password-123',
                'roles' => ['puskesmas'],
            ])
            ->assertRedirect('/users')
            ->assertSessionHas('success');

        $user = User::where('email', 'baru@instansi.go.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password-123', $user->password));
        $this->assertTrue($user->hasRole('puskesmas'));
    }

    public function test_update_menolak_ubah_role_sendiri(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['puskesmas'],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }

    public function test_destroy_menolak_akun_sendiri_dan_korbankan_transaksi(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->delete("/users/{$admin->id}")
            ->assertSessionHas('error');

        $other = User::factory()->create();
        $other->assignRole('puskesmas');
        PenerimaanStok::create([
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'user_id' => $other->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->delete("/users/{$other->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_reset_password_berhasil(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->create();
        $other->assignRole('puskesmas');

        $this->actingAs($admin)
            ->put("/users/{$other->id}/reset-password", [
                'password' => 'baru-12345',
                'password_confirmation' => 'baru-12345',
            ])
            ->assertRedirect('/users')
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('baru-12345', $other->fresh()->password));
    }
}
