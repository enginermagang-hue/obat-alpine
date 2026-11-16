<?php

namespace Tests\Feature\Master;

use App\Models\PenerimaanStok;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_index_dapat_diakses_admin_gudang_tanpa_tombol_hapus(): void
    {
        Supplier::factory()->create(['nama' => 'PT Uji Coba']);

        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get('/supplier')
            ->assertOk()
            ->assertSee('PT Uji Coba');
    }

    public function test_store_menolak_nama_duplikat_dan_email_salah(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Supplier::factory()->create(['nama' => 'PT Sama']);

        $this->actingAs($user)
            ->post('/supplier', ['nama' => 'PT Sama', 'status' => 'aktif'])
            ->assertSessionHasErrors('nama');

        $this->actingAs($user)
            ->post('/supplier', ['nama' => 'PT Baru', 'email' => 'bukan-email', 'status' => 'aktif'])
            ->assertSessionHasErrors('email');
    }

    public function test_deactivate_dan_destroy_guard(): void
    {
        $user = $this->userWithRole('super_admin');
        $supplier = Supplier::factory()->create(['status' => 'aktif']);

        $this->actingAs($user)
            ->patch("/supplier/{$supplier->id}/deactivate")
            ->assertRedirect('/supplier');

        $this->assertSame('nonaktif', $supplier->fresh()->getAttributes()['status']);

        PenerimaanStok::create([
            'supplier_id' => $supplier->id,
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete("/supplier/{$supplier->id}")
            ->assertRedirect('/supplier')
            ->assertSessionHas('error');
    }
}
