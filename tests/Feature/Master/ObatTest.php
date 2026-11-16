<?php

namespace Tests\Feature\Master;

use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObatTest extends TestCase
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

    public function test_index_dapat_diakses_admin_gudang(): void
    {
        $user = $this->userWithRole('admin_gudang');

        $this->actingAs($user)
            ->get('/obat')
            ->assertOk()
            ->assertSee('Master data obat', false);
    }

    public function test_tabel_json_filter_search(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Obat::factory()->create(['nama_obat' => 'Paracetamol Sirup Unik']);
        Obat::factory()->create(['nama_obat' => 'Amoxicillin Kapsul Unik']);

        $response = $this->actingAs($user)->getJson('/obat?search=Paracetamol');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Paracetamol Sirup Unik', $response->json('data.0.nama_obat'));
    }

    public function test_tabel_json_sort_dan_per_page(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Obat::factory()->create(['kode_obat' => 'OBT-ZZZ', 'nama_obat' => 'Zulu']);
        Obat::factory()->create(['kode_obat' => 'OBT-AAA', 'nama_obat' => 'Alpha']);

        $response = $this->actingAs($user)->getJson('/obat?sort=nama_obat&sortDir=desc&per_page=25');

        $response->assertOk();
        $this->assertSame(25, $response->json('meta.per_page'));
        $this->assertSame('Zulu', $response->json('data.0.nama_obat'));

        $response = $this->actingAs($user)->getJson('/obat?per_page=999');
        $this->assertSame(10, $response->json('meta.per_page'));
    }

    public function test_index_ditolak_untuk_puskesmas(): void
    {
        $user = $this->userWithRole('puskesmas');

        $this->actingAs($user)
            ->get('/obat')
            ->assertForbidden();
    }

    public function test_admin_dinas_hanya_baca(): void
    {
        $user = $this->userWithRole('admin_dinas');

        $this->actingAs($user)
            ->get('/obat')
            ->assertOk()
            ->assertDontSee('Tambah Obat', false);
    }

    public function test_store_menyimpan_obat_valid(): void
    {
        $user = $this->userWithRole('admin_gudang');

        $this->actingAs($user)
            ->post('/obat', [
                'kode_obat' => 'OBT-00001',
                'nama_obat' => 'Amoxicillin 500 mg',
                'kategori' => 'Antiinfeksi',
                'satuan' => 'Kapsul',
                'bentuk_sediaan' => 'kapsul',
                'status' => 'aktif',
                'metode_stok' => 'fefo',
            ])
            ->assertRedirect('/obat')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('obat', ['kode_obat' => 'OBT-00001']);
    }

    public function test_store_menolak_kode_duplikat(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Obat::factory()->create(['kode_obat' => 'OBT-DUP']);

        $this->actingAs($user)
            ->post('/obat', [
                'kode_obat' => 'OBT-DUP',
                'nama_obat' => 'Duplikat',
                'kategori' => 'Lainnya',
                'satuan' => 'Tablet',
                'bentuk_sediaan' => 'tablet',
                'status' => 'aktif',
                'metode_stok' => 'fifo',
            ])
            ->assertSessionHasErrors('kode_obat');
    }

    public function test_update_mengubah_data(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create(['nama_obat' => 'Nama Lama']);

        $this->actingAs($user)
            ->put("/obat/{$obat->id}", [
                'kode_obat' => $obat->kode_obat,
                'nama_obat' => 'Nama Baru',
                'kategori' => $obat->kategori,
                'satuan' => $obat->satuan,
                'bentuk_sediaan' => $obat->bentuk_sediaan,
                'status' => 'aktif',
                'metode_stok' => 'lifo',
            ])
            ->assertRedirect('/obat');

        $this->assertSame('Nama Baru', $obat->fresh()->nama_obat);
        $this->assertSame('lifo', $obat->fresh()->metode_stok->value);
    }

    public function test_deactivate_menonaktifkan_tanpa_menghapus(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create(['status' => 'aktif']);

        $this->actingAs($user)
            ->patch("/obat/{$obat->id}/deactivate")
            ->assertRedirect('/obat');

        $this->assertSame('nonaktif', $obat->fresh()->status);
        $this->assertDatabaseHas('obat', ['id' => $obat->id]);
    }

    public function test_destroy_ditolak_bila_punya_batch(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();
        BatchStok::create([
            'obat_id' => $obat->id,
            'batch_number' => 'B-001',
            'tanggal_expired' => now()->addYear()->toDateString(),
            'tanggal_masuk' => now()->toDateString(),
            'jumlah' => 10,
        ]);

        $this->actingAs($user)
            ->delete("/obat/{$obat->id}")
            ->assertRedirect('/obat')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('obat', ['id' => $obat->id]);
    }

    public function test_destroy_menghapus_bila_tanpa_batch(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();

        $this->actingAs($user)
            ->delete("/obat/{$obat->id}")
            ->assertRedirect('/obat')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('obat', ['id' => $obat->id]);
    }

    public function test_export_menghasilkan_xlsx(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Obat::factory()->create(['nama_obat' => 'Obat Export']);

        $response = $this->actingAs($user)->get('/obat/export');

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_filter_search_mempersempit_hasil(): void
    {
        $user = $this->userWithRole('admin_gudang');
        Obat::factory()->create(['nama_obat' => 'Paracetamol Sirup']);
        Obat::factory()->create(['nama_obat' => 'Amoxicillin Kapsul']);

        $response = $this->actingAs($user)->getJson('/obat?search=Paracetamol');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Paracetamol Sirup', $response->json('data.0.nama_obat'));
    }
}
