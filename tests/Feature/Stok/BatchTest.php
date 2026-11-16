<?php

namespace Tests\Feature\Stok;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BatchTest extends TestCase
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

    public function test_index_dapat_diakses_dan_filter_status(): void
    {
        BatchStok::factory()->create(['batch_number' => 'BTH-AKTIF-1', 'status' => 'tersedia']);
        BatchStok::factory()->create(['batch_number' => 'BTH-KAR-1', 'status' => 'karantina']);

        $response = $this->actingAs($this->userWithRole('admin_gudang'))->get('/batch?status=karantina');

        $response->assertOk()->assertSee('BTH-KAR-1');
        $response->assertDontSee('BTH-AKTIF-1', false);
    }

    public function test_pustu_hanya_melihat_batch_sendiri(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $pustu = FasilitasKesehatan::factory()->pustu($induk->id)->create();
        $obat = \App\Models\Obat::factory()->create(['nama_obat' => 'Obat Batch Pustu']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $pustu->id, 'batch_number' => 'BTH-PUSTU-1']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $induk->id, 'batch_number' => 'BTH-INDUK-1']);

        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);

        $response = $this->actingAs($user)->get('/batch');

        $response->assertOk()->assertSee('BTH-PUSTU-1');
        $response->assertDontSee('BTH-INDUK-1', false);
    }

    public function test_user_faskes_hanya_melihat_batch_sendiri(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        $obat = \App\Models\Obat::factory()->create(['nama_obat' => 'Obat Batch Alfa']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $faskesA->id, 'batch_number' => 'BTH-MILIK-A']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $faskesB->id, 'batch_number' => 'BTH-MILIK-B']);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->get('/batch');

        $response->assertOk()->assertSee('BTH-MILIK-A');
        $response->assertDontSee('BTH-MILIK-B', false);
    }

    public function test_filter_expiry_lewat(): void
    {
        BatchStok::factory()->create([
            'batch_number' => 'BTH-LEWAT-1',
            'tanggal_expired' => now()->subDays(5)->toDateString(),
        ]);
        BatchStok::factory()->create([
            'batch_number' => 'BTH-AMAN-1',
            'tanggal_expired' => now()->addYears(2)->toDateString(),
        ]);

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->get('/batch?expiry=lewat');

        $response->assertOk()->assertSee('BTH-LEWAT-1');
        $response->assertDontSee('BTH-AMAN-1', false);
    }
}
