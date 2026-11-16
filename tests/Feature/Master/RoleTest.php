<?php

namespace Tests\Feature\Master;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
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

    public function test_index_menampilkan_role_dan_ditolak_non_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/roles')
            ->assertOk()
            ->assertSee('Super Admin');

        $dinas = User::factory()->create();
        $dinas->assignRole(Role::findByName('admin_dinas'));

        $this->actingAs($dinas)
            ->get('/roles')
            ->assertOk()
            ->assertDontSee('Tambah Role', false);
    }

    public function test_store_membuat_role_dengan_permission(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/roles', [
                'name' => 'operator_baru',
                'permissions' => ['view_obat', 'view_dashboard'],
            ])
            ->assertRedirect('/roles')
            ->assertSessionHas('success');

        $role = Role::findByName('operator_baru');
        $this->assertTrue($role->hasPermissionTo('view_obat'));
        $this->assertFalse($role->hasPermissionTo('delete_obat'));
    }

    public function test_super_admin_dilindungi(): void
    {
        $admin = $this->superAdmin();
        $superAdmin = Role::findByName('super_admin');

        $this->actingAs($admin)
            ->put("/roles/{$superAdmin->id}", ['name' => 'super_admin'])
            ->assertRedirect('/roles')
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->delete("/roles/{$superAdmin->id}")
            ->assertRedirect('/roles')
            ->assertSessionHas('error');

        $this->assertTrue(Role::where('name', 'super_admin')->exists());
    }

    public function test_destroy_ditolak_bila_dipakai_user(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'dipakai_test', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($admin)
            ->delete("/roles/{$role->id}")
            ->assertSessionHas('error');

        $this->assertTrue(Role::where('name', 'dipakai_test')->exists());
        $this->assertSame(0, Permission::where('name', 'dipakai_test')->count());
    }
}
