<?php

namespace Tests\Feature\Laporan;

use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\PenerimaanStok;
use App\Models\TutupBuku;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TutupBukuTest extends TestCase
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

    public function test_index_json_dan_filter_status(): void
    {
        TutupBuku::factory()->create(['tahun' => 2024, 'tipe' => 'tahunan', 'status' => 'tutup']);
        TutupBuku::factory()->create(['tahun' => 2025, 'tipe' => 'tahunan', 'status' => 'dibuka_kembali']);

        $response = $this->actingAs($this->superAdmin())->getJson('/tutup-buku?status=tutup');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(2024, $response->json('data.0.tahun'));
    }

    public function test_tutup_tahun_mengunci_transaksi_lama(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/tutup-buku', [
            'tipe' => 'tahunan',
            'tahun' => 2025,
        ])->assertRedirect('/tutup-buku');

        $this->assertSame('tutup', TutupBuku::where('tahun', 2025)->first()->status);

        // Transaksi bertanggal tahun terkunci ditolak saat konfirmasi.
        $this->actingAs($admin)->post('/penerimaan', [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => '2025-06-15',
            'rows' => [[
                'obat_id' => Obat::factory()->create()->id,
                'batch_number' => 'B-001',
                'tanggal_expired' => now()->addYear()->toDateString(),
                'jumlah' => 10,
            ]],
        ])->assertRedirect();

        $penerimaan = PenerimaanStok::first();

        $this->actingAs($admin)
            ->patch("/penerimaan/{$penerimaan->id}/confirm")
            ->assertRedirect("/penerimaan/{$penerimaan->id}")
            ->assertSessionHas('error');

        $this->assertSame('draft', $penerimaan->fresh()->status);
        $this->assertSame(0, BatchStok::count());
    }

    public function test_buka_dan_tutup_kembali(): void
    {
        $admin = $this->superAdmin();
        $tutup = TutupBuku::factory()->create(['tahun' => 2025, 'tipe' => 'tahunan', 'status' => 'tutup']);

        $this->actingAs($admin)
            ->patch("/tutup-buku/{$tutup->id}/buka-kembali", ['alasan' => 'Koreksi data'])
            ->assertRedirect('/tutup-buku');
        $this->assertSame('dibuka_kembali', $tutup->fresh()->status);

        // Tanpa alasan ditolak.
        $tutup2 = TutupBuku::factory()->create(['tahun' => 2024, 'tipe' => 'tahunan', 'status' => 'tutup']);
        $this->actingAs($admin)
            ->patch("/tutup-buku/{$tutup2->id}/buka-kembali", [])
            ->assertSessionHasErrors('alasan');

        $this->actingAs($admin)->patch("/tutup-buku/{$tutup->id}/tutup-kembali");
        $this->assertSame('tutup', $tutup->fresh()->status);
    }

    public function test_admin_dinas_dapat_melihat(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_dinas'));

        $this->actingAs($user)
            ->get('/tutup-buku')
            ->assertOk()
            ->assertSee('Tutup Buku', false);
    }
}
