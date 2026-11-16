<?php

namespace Tests\Feature\Stok;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
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

        $response = $this->actingAs($this->userWithRole('admin_gudang'))->getJson('/batch?status=karantina');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('BTH-KAR-1', $response->json('data.0.batch_number'));
    }

    public function test_pustu_hanya_melihat_batch_sendiri(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $pustu = FasilitasKesehatan::factory()->pustu($induk->id)->create();
        $obat = Obat::factory()->create(['nama_obat' => 'Obat Batch Pustu']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $pustu->id, 'batch_number' => 'BTH-PUSTU-1']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $induk->id, 'batch_number' => 'BTH-INDUK-1']);

        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);

        $response = $this->actingAs($user)->getJson('/batch');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('BTH-PUSTU-1', $response->json('data.0.batch_number'));
    }

    public function test_user_faskes_hanya_melihat_batch_sendiri(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create();
        $faskesB = FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create(['nama_obat' => 'Obat Batch Alfa']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $faskesA->id, 'batch_number' => 'BTH-MILIK-A']);
        BatchStok::factory()->create(['obat_id' => $obat->id, 'fasilitas_id' => $faskesB->id, 'batch_number' => 'BTH-MILIK-B']);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/batch');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('BTH-MILIK-A', $response->json('data.0.batch_number'));
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

        $response = $this->actingAs($this->userWithRole('admin_dinas'))->getJson('/batch?expiry=lewat');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('BTH-LEWAT-1', $response->json('data.0.batch_number'));
    }
}
