<?php

namespace Tests\Feature\Transaksi;

use App\Models\BatchStok;
use App\Models\DistribusiObat;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PenerimaanStok;
use App\Models\PermintaanObat;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DistribusiTest extends TestCase
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

    private function gudangStock(Obat $obat, int $jumlah = 500): void
    {
        BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => null,
            'jumlah' => $jumlah,
            'status' => 'tersedia',
        ]);
        StokGudang::create(['obat_id' => $obat->id, 'jumlah' => $jumlah]);
    }

    public function test_index_json_dan_scope(): void
    {
        $this->actingAs($this->userWithRole('admin_gudang'))
            ->get('/distribusi')
            ->assertOk()
            ->assertSee('Distribusi', false);

        $response = $this->actingAs($this->userWithRole('admin_gudang'))->getJson('/distribusi?status=draft');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_store_mengalokasikan_batch_otomatis(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create(['metode_stok' => 'fefo']);
        $batchA = BatchStok::factory()->create([
            'obat_id' => $obat->id, 'fasilitas_id' => null, 'jumlah' => 30,
            'tanggal_expired' => now()->addMonth()->toDateString(), 'status' => 'tersedia',
        ]);
        $batchB = BatchStok::factory()->create([
            'obat_id' => $obat->id, 'fasilitas_id' => null, 'jumlah' => 100,
            'tanggal_expired' => now()->addYear()->toDateString(), 'status' => 'tersedia',
        ]);

        $this->actingAs($user)->post('/distribusi', [
            'tipe_distribusi' => 'dinas_ke_puskesmas',
            'fasilitas_penerima_id' => $faskes->id,
            'rows' => [['obat_id' => $obat->id, 'jumlah' => 50]],
        ])->assertRedirect();

        $distribusi = DistribusiObat::first();
        $details = $distribusi->details;
        // FEFO: batch A (expired duluan) habis dulu 30, sisa 20 dari batch B.
        $this->assertSame(2, $details->count());
        $this->assertSame($batchA->id, $details->firstWhere('batch_id', $batchA->id)->batch_id);
        $this->assertSame(30, $details->firstWhere('batch_id', $batchA->id)->jumlah);
        $this->assertSame(20, $details->firstWhere('batch_id', $batchB->id)->jumlah);
    }

    public function test_store_ditolak_bila_stok_kurang(): void
    {
        $user = $this->userWithRole('admin_gudang');
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $this->gudangStock($obat, 10);

        $this->actingAs($user)->post('/distribusi', [
            'tipe_distribusi' => 'dinas_ke_puskesmas',
            'fasilitas_penerima_id' => $faskes->id,
            'rows' => [['obat_id' => $obat->id, 'jumlah' => 100]],
        ])->assertStatus(422);

        $this->assertSame(0, DistribusiObat::count());
    }

    public function test_alur_kirim_terima_memindahkan_stok(): void
    {
        $gudang = $this->userWithRole('admin_gudang');
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $penerima = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskes->id]);
        $obat = Obat::factory()->create();
        $this->gudangStock($obat, 100);

        // Permintaan disetujui → distribusi prefill.
        $permintaan = PermintaanObat::factory()->create([
            'fasilitas_pengirim_id' => $faskes->id,
            'tipe_permintaan' => 'puskesmas_ke_dinas',
            'status' => 'disetujui',
        ]);

        $this->actingAs($gudang)->post('/distribusi', [
            'tipe_distribusi' => 'dinas_ke_puskesmas',
            'fasilitas_penerima_id' => $faskes->id,
            'permintaan_id' => $permintaan->id,
            'rows' => [['obat_id' => $obat->id, 'jumlah' => 40]],
        ])->assertRedirect();

        $distribusi = DistribusiObat::first();

        // Kirim.
        $this->actingAs($gudang)->patch("/distribusi/{$distribusi->id}/kirim");
        $this->assertSame('dalam_pengiriman', $distribusi->fresh()->status);
        $this->assertSame('sedang_didistribusi', $permintaan->fresh()->status);

        // Buat penerimaan oleh penerima.
        $this->actingAs($penerima)->post("/distribusi/{$distribusi->id}/buat-penerimaan")
            ->assertRedirect();

        $penerimaan = PenerimaanStok::where('distribusi_id', $distribusi->id)->first();
        $this->assertNotNull($penerimaan);
        $this->assertSame($faskes->id, $penerimaan->fasilitas_id);

        // Konfirmasi penerimaan → stok pindah.
        $this->actingAs($penerima)->patch("/penerimaan/{$penerimaan->id}/confirm");

        $this->assertSame('diterima', $distribusi->fresh()->status);
        $this->assertSame('diterima', $permintaan->fresh()->status);
        $this->assertSame(60, StokGudang::where('obat_id', $obat->id)->first()->jumlah);
        $this->assertSame(40, StokFaskes::where('fasilitas_id', $faskes->id)->where('obat_id', $obat->id)->first()->jumlah);
    }

    public function test_tolak_mengembalikan_permintaan(): void
    {
        $gudang = $this->userWithRole('admin_gudang');
        $faskes = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $this->gudangStock($obat, 100);

        $permintaan = PermintaanObat::factory()->create([
            'fasilitas_pengirim_id' => $faskes->id,
            'tipe_permintaan' => 'puskesmas_ke_dinas',
            'status' => 'disetujui',
        ]);

        $this->actingAs($gudang)->post('/distribusi', [
            'tipe_distribusi' => 'dinas_ke_puskesmas',
            'fasilitas_penerima_id' => $faskes->id,
            'permintaan_id' => $permintaan->id,
            'rows' => [['obat_id' => $obat->id, 'jumlah' => 10]],
        ]);

        $distribusi = DistribusiObat::first();
        $this->actingAs($gudang)->patch("/distribusi/{$distribusi->id}/kirim");

        $this->actingAs($gudang)->patch("/distribusi/{$distribusi->id}/tolak", ['alasan' => 'Alamat salah']);

        $this->assertSame('ditolak', $distribusi->fresh()->status);
        $this->assertSame('disetujui', $permintaan->fresh()->status);
    }

    public function test_pustu_tidak_bisa_buat_distribusi(): void
    {
        $pustu = FasilitasKesehatan::factory()->pustu()->create();
        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);

        $this->actingAs($user)->get('/distribusi/create')->assertForbidden();
    }

    public function test_lookup_alokasi_menghormati_scope(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $faskesB = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $obat = Obat::factory()->create();
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $this->actingAs($user)
            ->getJson("/api/distribusi/alokasi?obat_id={$obat->id}&jumlah=10&fasilitas_id={$faskesB->id}")
            ->assertForbidden();
    }
}
