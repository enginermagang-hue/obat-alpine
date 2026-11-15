<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferensi_profil_dapat_disimpan(): void
    {
        $user = User::factory()->create(['name' => 'Nama Lama']);

        $this->actingAs($user)
            ->put('/profil', [
                'name' => 'Nama Baru',
                'avatar_type' => 'dicebear',
                'avatar_dicebear_style' => 'micah',
                'posisi_navbar' => 'sidebar',
                'sidebar_collapsed' => '1',
                'bahasa' => 'id',
                'items_per_halaman' => 25,
                'notifikasi_email' => '1',
            ])
            ->assertRedirect('/profil')
            ->assertSessionHas('success');

        $this->assertSame('Nama Baru', $user->fresh()->name);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'avatar_type' => 'dicebear',
            'avatar_dicebear_style' => 'micah',
            'items_per_halaman' => 25,
        ]);
    }

    public function test_password_dapat_diubah(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $this->actingAs($user)
            ->put('/profil/password', [
                'current_password' => 'password-lama',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertRedirect('/profil');

        $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
    }

    public function test_password_lama_salah_ditolak(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-lama'),
        ]);

        $this->actingAs($user)
            ->put('/profil/password', [
                'current_password' => 'salah',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
