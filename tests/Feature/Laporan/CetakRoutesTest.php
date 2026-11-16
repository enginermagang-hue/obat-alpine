<?php

namespace Tests\Feature\Laporan;

use App\Models\DistribusiObat;
use App\Models\PenerimaanStok;
use App\Models\PermintaanObat;
use App\Models\ReturObat;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CetakRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_semua_route_cetak_terdaftar(): void
    {
        foreach ([
            'lplpo.cetak',
            'rko.cetak-pdf', 'rko.cetak-xls',
            'neraca.cetak-pdf', 'neraca.cetak-xls',
            'distribusi.cetak',
            'penerimaan.cetak',
            'retur.cetak',
            'permintaan.cetak', 'permintaan.surat',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} belum terdaftar.");
        }
    }

    public function test_faktur_draft_mengembalikan_404(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('super_admin'));

        $distribusi = DistribusiObat::factory()->create(['status' => 'draft']);
        $penerimaan = PenerimaanStok::factory()->create(['status' => 'draft']);
        $retur = ReturObat::factory()->create(['status' => 'draft']);
        $permintaan = PermintaanObat::factory()->create(['status' => 'draft']);

        $this->actingAs($admin)->get("/cetak/distribusi/{$distribusi->id}")->assertNotFound();
        $this->actingAs($admin)->get("/cetak/penerimaan/{$penerimaan->id}")->assertNotFound();
        $this->actingAs($admin)->get("/cetak/retur/{$retur->id}")->assertNotFound();
        $this->actingAs($admin)->get("/cetak/permintaan/{$permintaan->id}")->assertNotFound();
    }
}
