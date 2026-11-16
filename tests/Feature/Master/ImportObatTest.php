<?php

namespace Tests\Feature\Master;

use App\Jobs\ImportObatJob;
use App\Models\Obat;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportObatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_job_upsert_by_kode_obat(): void
    {
        Storage::fake('local');

        Obat::factory()->create(['kode_obat' => 'OBT-LAMA', 'nama_obat' => 'Nama Lama']);

        $csv = "kode_obat,nama_obat,nama_generik,kategori,satuan,kekuatan,bentuk_sediaan,produsen,kemasan,harga_satuan,status,ven_kategori,metode_stok\n"
            ."OBT-LAMA,Nama Baru,Generik,Antibiotik,Kapsul,500 mg,kapsul,PT X,Dus,3000,aktif,E,fifo\n"
            ."OBT-BARU,Obat Baru,,Vitamin,Tablet,100 mg,tablet,,,1500,aktif,V,fefo\n"
            .",Tanpa Kode,,Lainnya,Tablet,,tablet,,,1000,aktif,,fefo\n";

        Storage::disk('local')->put('imports/obat-test.csv', $csv);

        $importId = DB::table('imports')->insertGetId([
            'file_name' => 'obat-test.csv',
            'file_path' => 'imports/obat-test.csv',
            'importer' => 'obat',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportObatJob::dispatchSync($importId);

        $lama = Obat::where('kode_obat', 'OBT-LAMA')->first();
        $this->assertSame('Nama Baru', $lama->nama_obat);
        $this->assertSame('Generik', $lama->nama_generik);
        $this->assertDatabaseHas('obat', ['kode_obat' => 'OBT-BARU', 'ven_kategori' => 'V']);

        $status = DB::table('imports')->where('id', $importId)->first();
        $this->assertSame(3, $status->processed_rows);
        $this->assertSame(2, $status->successful_rows);
        $this->assertNotNull($status->completed_at);
        $this->assertTrue(Storage::disk('local')->exists('imports/'.$importId.'-gagal.csv'));
    }

    public function test_upload_endpoint_menerima_csv(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_gudang'));

        $this->actingAs($user)
            ->post('/obat/import', [
                'file' => UploadedFile::fake()->createWithContent('obat.csv', "kode_obat,nama_obat\nOBT-1,Obat Satu\n"),
            ])
            ->assertRedirect('/obat')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('imports', ['importer' => 'obat', 'file_name' => 'obat.csv']);
    }

    public function test_template_dapat_diunduh(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_gudang'));

        $response = $this->actingAs($user)->get('/obat/import/template');

        $response->assertOk();
        $this->assertStringContainsString('kode_obat', $response->streamedContent());
    }
}
