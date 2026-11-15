<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialiteUser;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return back()->with('error', 'Login Google belum dikonfigurasi.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with('error', 'Login Google belum dikonfigurasi.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('[Google] gagal mengambil data user', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Gagal masuk dengan Google. Silakan coba lagi.');
        }

        $user = $request->user() ?? Auth::user();

        if ($user === null) {
            $localUser = User::query()->where('email', $googleUser->getEmail())->first();

            if ($localUser === null) {
                return redirect()->route('login')
                    ->with('error', 'Email Google Anda belum terdaftar. Hubungi administrator.');
            }

            Auth::login($localUser, true);
            $request->session()->regenerate();
            app(ActivityLogService::class)->userLogin($localUser);

            return redirect()->intended(route('dashboard'));
        }

        $existing = SocialiteUser::query()
            ->where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            return redirect()->route('profil.edit')
                ->with('error', 'Akun Google ini sudah terhubung dengan pengguna lain.');
        }

        SocialiteUser::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => 'google'],
            ['provider_id' => $googleUser->getId()],
        );

        return redirect()->route('profil.edit')->with('success', 'Akun Google berhasil dihubungkan.');
    }

    public function unlink(Request $request): RedirectResponse
    {
        SocialiteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'google')
            ->delete();

        return redirect()->route('profil.edit')->with('success', 'Akun Google berhasil diputuskan.');
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
