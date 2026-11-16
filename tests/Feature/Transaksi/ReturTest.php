<?php

namespace Tests\Feature\Transaksi;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\ReturObat;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('public');
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function stokBatch(Obat $obat, ?int $faskesId, int $jumlah = 100): BatchStok
    {
        $batch = BatchStok::factory()->create([
            'obat_id' => $obat->id,
            'fasilitas_id' => $faskesId,
            'jumlah' => $jumlah,
            'status' => 'tersedia',
        ]);

        if ($faskesId) {
            StokFaskes::create(['fasilitas_id' => $faskesId, 'obat_id' => $obat->id, 'jumlah' => $jumlah]);
        } else {
            StokGudang::create(['obat_id' => $obat->id, 'jumlah' => $jumlah]);
        }

        return $batch;
    }

    private function rows(Obat $obat, BatchStok $batch, int $jumlah = 20): array
    {
        return [[
            'obat_id' => $obat->id,
            'batch_id' => $batch->id,
            'jumlah_retur' => $jumlah,
        ]];
    }

    public function test_index_json_dan_scope_puskesmas(): void
    {
        $faskesA = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $faskesB = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        ReturObat::factory()->create(['fasilitas_pengirim_id' => $faskesB->id, 'tipe_retur' => 'puskesmas_ke_gudang']);

        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $faskesA->id]);

        $response = $this->actingAs($user)->getJson('/retur');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_alur_penuh_puskesmas_ke_gudang(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);
        $dinas = $this->userWithRole('admin_dinas');
        $obat = Obat::factory()->create(['metode_stok' => 'fefo']);
        $batch = $this->stokBatch($obat, $puskesmas->id, 100);

        // Buat draft (tipe otomatis puskesmas_ke_gudang).
        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'puskesmas_ke_gudang',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'kelebihan_stok',
            'rows' => $this->rows($obat, $batch, 20),
        ])->assertRedirect();

        $retur = ReturObat::first();
        $this->assertSame('draft', $retur->status);
        $this->assertNull($retur->fasilitas_penerima_id);

        // Ajukan → menunggu_approval.
        $this->actingAs($user)->patch("/retur/{$retur->id}/submit");
        $this->assertSame('menunggu_approval', $retur->fresh()->status);

        // Dinas setujui.
        $this->actingAs($dinas)->patch("/retur/{$retur->id}/approve");
        $this->assertSame('disetujui', $retur->fresh()->status);

        // Pengirim kirim.
        $this->actingAs($user)->patch("/retur/{$retur->id}/send");
        $this->assertSame('dalam_pengiriman', $retur->fresh()->status);

        // Gudang terima → stok pindah.
        $this->actingAs($dinas)->patch("/retur/{$retur->id}/receive");
        $this->assertSame('diterima', $retur->fresh()->status);
        $this->assertSame(80, $batch->fresh()->jumlah);
        $this->assertSame(80, StokFaskes::where('fasilitas_id', $puskesmas->id)->where('obat_id', $obat->id)->first()->jumlah);
        $this->assertSame(20, StokGudang::where('obat_id', $obat->id)->first()->jumlah);

        // Selesaikan.
        $this->actingAs($dinas)->patch("/retur/{$retur->id}/complete");
        $this->assertSame('selesai', $retur->fresh()->status);
    }

    public function test_tolak_butuh_alasan(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);
        $dinas = $this->userWithRole('admin_dinas');
        $obat = Obat::factory()->create();
        $batch = $this->stokBatch($obat, $puskesmas->id, 100);

        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'puskesmas_ke_gudang',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'rusak',
            'rows' => $this->rows($obat, $batch, 10),
        ]);

        $retur = ReturObat::first();
        $this->actingAs($user)->patch("/retur/{$retur->id}/submit");

        $this->actingAs($dinas)->patch("/retur/{$retur->id}/reject", [])->assertSessionHasErrors('alasan_penolakan');

        $this->actingAs($dinas)->patch("/retur/{$retur->id}/reject", ['alasan_penolakan' => 'Kondisi masih baik']);
        $this->assertSame('ditolak', $retur->fresh()->status);
    }

    public function test_jumlah_melebihi_stok_ditolak(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);
        $obat = Obat::factory()->create();
        $batch = $this->stokBatch($obat, $puskesmas->id, 10);

        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'puskesmas_ke_gudang',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'rusak',
            'rows' => $this->rows($obat, $batch, 50),
        ])->assertStatus(422);

        $this->assertSame(0, ReturObat::count());
    }

    public function test_upload_bukti_foto(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);
        $obat = Obat::factory()->create();
        $batch = $this->stokBatch($obat, $puskesmas->id, 100);

        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'puskesmas_ke_gudang',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'rusak',
            'rows' => [[
                'obat_id' => $obat->id,
                'batch_id' => $batch->id,
                'jumlah_retur' => 10,
                'bukti' => UploadedFile::fake()->image('rusak.jpg'),
            ]],
        ])->assertRedirect();

        $detail = ReturObat::first()->details()->first();
        $this->assertNotNull($detail->bukti_foto);
        Storage::disk('public')->assertExists($detail->bukti_foto);
    }

    public function test_alasan_lainnya_wajib_dijelaskan(): void
    {
        $puskesmas = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $user = $this->userWithRole('puskesmas', ['fasilitas_kesehatan_id' => $puskesmas->id]);
        $obat = Obat::factory()->create();
        $batch = $this->stokBatch($obat, $puskesmas->id, 100);

        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'puskesmas_ke_gudang',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'lainnya',
            'rows' => $this->rows($obat, $batch, 10),
        ])->assertSessionHasErrors('alasan_lainnya');
    }

    public function test_pustu_hanya_bisa_tipe_sendiri(): void
    {
        $induk = FasilitasKesehatan::factory()->create(['tipe' => 'puskesmas']);
        $pustu = FasilitasKesehatan::factory()->pustu($induk->id)->create();
        $user = $this->userWithRole('pustu', ['fasilitas_kesehatan_id' => $pustu->id]);
        $obat = Obat::factory()->create();
        $batch = $this->stokBatch($obat, $pustu->id, 100);

        // Coba tipe gudang_ke_supplier → ditolak 422.
        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'gudang_ke_supplier',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'expired',
            'rows' => $this->rows($obat, $batch, 10),
        ])->assertStatus(422);

        // Tipe benar → penerima otomatis induk.
        $this->actingAs($user)->post('/retur', [
            'tipe_retur' => 'pustu_ke_puskesmas',
            'tanggal_retur' => now()->toDateString(),
            'alasan' => 'near_expiry',
            'rows' => $this->rows($obat, $batch, 10),
        ])->assertRedirect();

        $retur = ReturObat::first();
        $this->assertSame($induk->id, $retur->fasilitas_penerima_id);
    }
}
