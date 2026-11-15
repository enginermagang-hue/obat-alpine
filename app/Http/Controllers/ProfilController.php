<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilUpdateRequest;
use App\Models\SocialiteUser;
use App\Models\UserPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfilController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->loadMissing('preferences', 'fasilitasKesehatan', 'socialiteUsers');

        $prefs = $user->preferences ?? new UserPreference(UserPreference::defaults());

        return view('profil.edit', [
            'user' => $user,
            'prefs' => $prefs,
            'google' => $user->socialiteUsers->firstWhere('provider', 'google'),
            'googleConfigured' => filled(config('services.google.client_id')),
            'styles' => UserPreference::dicebearStyles(),
        ]);
    }

    public function update(ProfilUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update(['name' => $data['name']]);

        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'avatar_type' => $data['avatar_type'],
                'avatar_path' => $data['avatar_path'] ?? null,
                'avatar_dicebear_style' => $data['avatar_dicebear_style'] ?? 'avataaars',
                'posisi_navbar' => $data['posisi_navbar'],
                'sidebar_collapsed' => $data['sidebar_collapsed'],
                'bahasa' => $data['bahasa'],
                'items_per_halaman' => $data['items_per_halaman'],
                'notifikasi_email' => $data['notifikasi_email'],
                'notifikasi_browser' => $data['notifikasi_browser'],
            ],
        );

        return redirect()->route('profil.edit')->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Password saat ini tidak cocok.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('profil.edit')->with('success', 'Password berhasil diubah.');
    }

    public function unlinkGoogle(Request $request): RedirectResponse
    {
        SocialiteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'google')
            ->delete();

        return redirect()->route('profil.edit')->with('success', 'Akun Google berhasil diputuskan.');
    }
}
