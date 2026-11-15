<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_dapat_diakses_user_login(): void
    {
        $user = User::factory()->create(['name' => 'Petugas Gudang']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Petugas Gudang')
            ->assertSee('Batch mendekati kedaluwarsa');
    }

    public function test_halaman_profil_dapat_diakses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profil')
            ->assertOk()
            ->assertSee('Tampilan & preferensi');
    }
}
