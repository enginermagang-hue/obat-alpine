@extends('layouts.app')

@section('title', 'Profil & Preferensi')
@section('subtitle', 'Kelola akun, tampilan, dan notifikasi')

@section('content')
    @php
        $dicebearBase = 'https://api.dicebear.com/10.x';
    @endphp

    <div class="grid gap-6 xl:grid-cols-3" x-data="{
        avatarType: @js(old('avatar_type', $prefs->avatar_type)),
        style: @js(old('avatar_dicebear_style', $prefs->avatar_dicebear_style ?? 'avataaars')),
        initials: @js($user->initials()),
    }">
        <form method="POST" action="{{ route('profil.update') }}" class="space-y-6 xl:col-span-2">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Informasi akun</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Nama tampil dan identitas fasilitas.</p>
                </div>

                <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="form-label">Nama</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            required
                            class="form-input"
                        >
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            class="form-input"
                        >
                    </div>

                    <div>
                        <label class="form-label">Fasilitas</label>
                        <input
                            type="text"
                            value="{{ $user->fasilitasKesehatan?->nama ?? 'Gudang Farmasi Dinas' }}"
                            disabled
                            class="form-input"
                        >
                    </div>

                    <div>
                        <label class="form-label">Role</label>
                        <input
                            type="text"
                            value="{{ $user->getRoleNames()->map(fn ($r) => str($r)->replace('_', ' ')->title())->implode(', ') ?: '-' }}"
                            disabled
                            class="form-input"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Avatar</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pilih tampilan foto profil Anda.</p>
                </div>

                <div class="space-y-5 px-5 py-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (['initials' => 'Inisial nama', 'dicebear' => 'DiceBear', 'preset' => 'Preset'] as $value => $label)
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition"
                                :class="avatarType === '{{ $value }}'
                                    ? 'border-brand-500 bg-brand-50/60 text-brand-700 dark:border-brand-500/60 dark:bg-brand-500/10 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300'"
                            >
                                <input type="radio" name="avatar_type" value="{{ $value }}" x-model="avatarType" class="form-radio">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div x-show="avatarType === 'dicebear'" x-cloak>
                        <label for="avatar_dicebear_style" class="form-label">Gaya DiceBear</label>
                        <select
                            id="avatar_dicebear_style"
                            name="avatar_dicebear_style"
                            x-model="style"
                            class="form-select"
                        >
                            @foreach ($styles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="avatarType === 'preset'" x-cloak>
                        <label for="avatar_path" class="form-label">Path preset</label>
                        <input
                            id="avatar_path"
                            name="avatar_path"
                            type="text"
                            value="{{ old('avatar_path', $prefs->avatar_path) }}"
                            placeholder="assets/images/avatars/boy/1.png"
                            class="form-input"
                        >
                        @error('avatar_path')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('avatar_type')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Tampilan &amp; preferensi</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sesuaikan navigasi dan daftar data.</p>
                </div>

                <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <label for="bahasa" class="form-label">Bahasa</label>
                        <select id="bahasa" name="bahasa" data-tomselect-search="false" class="form-select">
                            @foreach (['id' => 'Indonesia', 'en' => 'English'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('bahasa', $prefs->bahasa) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="posisi_navbar" class="form-label">Posisi menu</label>
                        <select id="posisi_navbar" name="posisi_navbar" data-tomselect-search="false" class="form-select">
                            <option value="sidebar" @selected(old('posisi_navbar', $prefs->posisi_navbar) === 'sidebar')>Sidebar</option>
                            <option value="topbar" @selected(old('posisi_navbar', $prefs->posisi_navbar) === 'topbar')>Topbar</option>
                        </select>
                    </div>

                    <div>
                        <label for="items_per_halaman" class="form-label">Item per halaman</label>
                        <select id="items_per_halaman" name="items_per_halaman" data-tomselect-search="false" class="form-select">
                            @foreach ([10, 25, 50, 100] as $option)
                                <option value="{{ $option }}" @selected((int) old('items_per_halaman', $prefs->items_per_halaman) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-3 sm:pt-7">
                        <label class="form-check">
                            <input type="checkbox" name="sidebar_collapsed" value="1" @checked(old('sidebar_collapsed', $prefs->sidebar_collapsed)) class="form-checkbox">
                            Ciutkan sidebar secara default
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="notifikasi_email" value="1" @checked(old('notifikasi_email', $prefs->notifikasi_email)) class="form-checkbox">
                            Notifikasi email
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="notifikasi_browser" value="1" @checked(old('notifikasi_browser', $prefs->notifikasi_browser)) class="form-checkbox">
                            Notifikasi browser
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Simpan perubahan
                    </button>
                </div>
            </section>
        </form>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto flex h-24 w-24 items-center justify-center">
                    <template x-if="avatarType === 'initials'">
                        <span class="grid h-24 w-24 place-items-center rounded-3xl bg-brand-500/10 text-2xl font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-200" x-text="initials"></span>
                    </template>
                    <template x-if="avatarType === 'dicebear'">
                        <img :src="`{{ $dicebearBase }}/${style}/svg?seed={{ urlencode($user->email) }}`" alt="Avatar" class="h-24 w-24 rounded-3xl bg-slate-100 object-cover dark:bg-slate-800">
                    </template>
                    <template x-if="avatarType === 'preset'">
                        <img src="{{ $prefs->avatar_path ? asset($prefs->avatar_path) : asset('assets/images/avatars/boy/1.png') }}" alt="Avatar" class="h-24 w-24 rounded-3xl bg-slate-100 object-cover dark:bg-slate-800">
                    </template>
                </div>

                <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                <p class="mt-3 inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ $user->getRoleNames()->map(fn ($r) => str($r)->replace('_', ' ')->title())->implode(', ') ?: 'Tanpa role' }}
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Akun Google</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Hubungkan untuk login cepat.</p>
                </div>

                <div class="space-y-3 px-5 py-5 text-sm">
                    @if ($google)
                        <p class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                            <x-heroicon-o-check-badge class="h-4 w-4" />
                            Terhubung
                        </p>
                        <form method="POST" action="{{ route('google.unlink') }}">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block btn-sm">
                                Putuskan akun Google
                            </button>
                        </form>
                    @else
                        <p class="text-slate-500 dark:text-slate-400">Belum terhubung.</p>
                        @if ($googleConfigured)
                            <a href="{{ route('google.redirect') }}" class="btn btn-secondary btn-block btn-sm">
                                <x-heroicon-o-link class="h-4 w-4" />
                                Hubungkan Google
                            </a>
                        @else
                            <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                Kredensial Google (GOOGLE_CLIENT_ID/SECRET) belum diisi.
                            </p>
                        @endif
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Ubah password</h2>
                </div>

                <form method="POST" action="{{ route('profil.password') }}" class="space-y-3 px-5 py-5">
                    @csrf
                    @method('PUT')

                    <input
                        type="password"
                        name="current_password"
                        placeholder="Password saat ini"
                        autocomplete="current-password"
                        class="form-input"
                    >
                    @error('current_password')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <input
                        type="password"
                        name="password"
                        placeholder="Password baru (min. 8 karakter)"
                        autocomplete="new-password"
                        class="form-input"
                    >
                    @error('password')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <input
                        type="password"
                        name="password_confirmation"
                        placeholder="Ulangi password baru"
                        autocomplete="new-password"
                        class="form-input"
                    >

                    <button type="submit" class="btn btn-dark btn-block btn-sm">
                        Perbarui password
                    </button>
                </form>
            </section>
        </div>
    </div>
@endsection
