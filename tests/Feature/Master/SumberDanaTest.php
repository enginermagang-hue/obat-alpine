<?php

namespace Tests\Feature\Master;

use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\SumberDana;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SumberDanaTest extends TestCase
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

    public function test_index_dapat_diakses_dan_filter_tahun(): void
    {
        SumberDana::factory()->create(['kode' => 'DN-A', 'tahun' => 2025]);
        SumberDana::factory()->create(['kode' => 'DN-B', 'tahun' => 2026]);

        $this->actingAs($this->userWithRole('admin_dinas'))
            ->get('/sumber-dana')
            ->assertOk()
            ->assertSee('Sumber Dana', false);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/sumber-dana?tahun=2025');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('DN-A', $response->json('data.0.kode'));
    }

    public function test_store_menolak_kode_tahun_duplikat(): void
    {
        $user = $this->userWithRole('admin_dinas');
        SumberDana::factory()->create(['kode' => 'DN-DUP', 'tahun' => 2026]);

        // Sama kode + tahun → ditolak
        $this->actingAs($user)
            ->post('/sumber-dana', [
                'kode' => 'DN-DUP', 'nama' => 'Duplikat', 'tahun' => 2026,
                'total_anggaran' => 1000000, 'status' => 'aktif',
            ])
            ->assertSessionHasErrors('tahun');

        // Sama kode beda tahun → boleh
        $this->actingAs($user)
            ->post('/sumber-dana', [
                'kode' => 'DN-DUP', 'nama' => 'Tahun lain', 'tahun' => 2027,
                'total_anggaran' => 1000000, 'status' => 'aktif',
            ])
            ->assertRedirect('/sumber-dana');

        $this->assertDatabaseHas('sumber_dana', ['kode' => 'DN-DUP', 'tahun' => 2027]);
    }

    public function test_status_nonaktif_tersimpan_benar(): void
    {
        $user = $this->userWithRole('admin_dinas');

        $this->actingAs($user)
            ->post('/sumber-dana', [
                'kode' => 'DN-STAT', 'nama' => 'Uji Status', 'tahun' => 2026,
                'total_anggaran' => 500000, 'status' => 'nonaktif',
            ])
            ->assertRedirect('/sumber-dana');

        $row = SumberDana::where('kode', 'DN-STAT')->first();
        $this->assertSame('nonaktif', $row->getAttributes()['status']);
        $this->assertFalse($row->status);
    }

    public function test_deactivate_dan_destroy_guard(): void
    {
        $user = $this->userWithRole('admin_dinas');
        $dana = SumberDana::factory()->create(['status' => 'aktif']);

        $this->actingAs($user)
            ->patch("/sumber-dana/{$dana->id}/deactivate")
            ->assertRedirect('/sumber-dana');

        $this->assertSame('nonaktif', $dana->fresh()->getAttributes()['status']);

        // Dipakai batch → hapus ditolak
        BatchStok::create([
            'obat_id' => Obat::factory()->create()->id,
            'sumber_dana_id' => $dana->id,
            'batch_number' => 'B-001',
            'tanggal_expired' => now()->addYear()->toDateString(),
            'tanggal_masuk' => now()->toDateString(),
            'jumlah' => 5,
        ]);

        $this->actingAs($user)
            ->delete("/sumber-dana/{$dana->id}")
            ->assertRedirect('/sumber-dana')
            ->assertSessionHas('error');
    }
}
