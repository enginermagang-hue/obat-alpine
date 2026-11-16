<?php

namespace Tests\Feature\Transaksi;

use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\PenerimaanStok;
use App\Models\StokGudang;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PenerimaanTest extends TestCase
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

    private function rows(Obat $obat, int $jumlah = 50): array
    {
        return [[
            'obat_id' => $obat->id,
            'batch_number' => 'BTH-UJI-1',
            'tanggal_expired' => now()->addYear()->toDateString(),
            'jumlah' => $jumlah,
            'harga_satuan' => 2000,
        ]];
    }

    public function test_index_json_dan_shell(): void
    {
        $user = $this->userWithRole('admin_gudang');

        $this->actingAs($user)->get('/penerimaan')->assertOk()->assertSee('Penerimaan', false);

        $response = $this->actingAs($user)->getJson('/penerimaan?status=draft');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_store_membuat_draft_dengan_total_server_side(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();

        // Kirim total ngawur — server harus menghitung ulang.
        $this->actingAs($user)
            ->post('/penerimaan', [
                'tipe' => 'pembelian',
                'tanggal_penerimaan' => now()->toDateString(),
                'rows' => $this->rows($obat, 50),
                'total_biaya' => 1,
            ])
            ->assertRedirect();

        $penerimaan = PenerimaanStok::first();
        $this->assertSame('draft', $penerimaan->status);
        $this->assertEquals(100000, (float) $penerimaan->fresh()->total_biaya);
        $this->assertNotNull($penerimaan->nomor_penerimaan);
    }

    public function test_konfirmasi_menambah_stok_batch_riwayat(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();

        $this->actingAs($user)->post('/penerimaan', [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 50),
        ]);

        $penerimaan = PenerimaanStok::first();

        $this->actingAs($user)
            ->patch("/penerimaan/{$penerimaan->id}/confirm")
            ->assertRedirect("/penerimaan/{$penerimaan->id}");

        $this->assertSame('dikonfirmasi', $penerimaan->fresh()->status);
        $this->assertSame(50, BatchStok::where('obat_id', $obat->id)->first()->jumlah);
        $this->assertSame(50, StokGudang::where('obat_id', $obat->id)->first()->jumlah);
        $this->assertDatabaseHas('riwayat_stok', [
            'referensi_type' => PenerimaanStok::class,
            'referensi_id' => $penerimaan->id,
            'tipe' => 'masuk',
        ]);
    }

    public function test_batal_mengembalikan_stok(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();

        $this->actingAs($user)->post('/penerimaan', [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 50),
        ]);

        $penerimaan = PenerimaanStok::first();
        $this->actingAs($user)->patch("/penerimaan/{$penerimaan->id}/confirm");
        $this->actingAs($user)->patch("/penerimaan/{$penerimaan->id}/cancel");

        $this->assertSame('dibatalkan', $penerimaan->fresh()->status);
        $this->assertSame(0, BatchStok::where('obat_id', $obat->id)->first()->jumlah);
        $this->assertSame(0, StokGudang::where('obat_id', $obat->id)->first()->jumlah);
    }

    public function test_edit_ditolak_setelah_dikonfirmasi(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $obat = Obat::factory()->create();

        $this->actingAs($user)->post('/penerimaan', [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 50),
        ]);

        $penerimaan = PenerimaanStok::first();
        $this->actingAs($user)->patch("/penerimaan/{$penerimaan->id}/confirm");

        $this->actingAs($user)
            ->put("/penerimaan/{$penerimaan->id}", [
                'tipe' => 'pembelian',
                'tanggal_penerimaan' => now()->toDateString(),
                'rows' => $this->rows($obat, 10),
            ])
            ->assertForbidden();
    }

    public function test_user_faskes_terkunci_di_faskes_sendiri(): void
    {
        $faskesA = \App\Models\FasilitasKesehatan::factory()->create();
        $faskesB = \App\Models\FasilitasKesehatan::factory()->create();
        $obat = Obat::factory()->create();
        PenerimaanStok::factory()->create(['fasilitas_id' => $faskesB->id]);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/penerimaan');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));

        // Terima di faskes sendiri → tercatat di faskes sendiri, bukan gudang.
        $this->actingAs($user)->post('/penerimaan', [
            'tipe' => 'pembelian',
            'tanggal_penerimaan' => now()->toDateString(),
            'rows' => $this->rows($obat, 20),
        ])->assertRedirect();

        $penerimaan = PenerimaanStok::latest('id')->first();
        $this->assertSame($faskesA->id, $penerimaan->fasilitas_id);
    }
}
