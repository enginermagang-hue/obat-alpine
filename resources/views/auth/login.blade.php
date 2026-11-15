<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-screen">

        {{-- Panel branding --}}
        <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-accent-700 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -right-16 h-96 w-96 rounded-full bg-accent-400/20 blur-3xl"></div>

            <div class="relative z-10 p-12">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white/15 text-sm font-bold text-white ring-1 ring-white/20">
                        RO
                    </span>
                    <div>
                        <p class="text-sm font-semibold tracking-wide text-white">RUANG OBAT</p>
                        <p class="text-xs text-white/70">Dinas Kesehatan Kabupaten Kupang</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 px-12">
                <h1 class="max-w-md text-3xl font-semibold leading-tight text-white">
                    Kelola stok obat dari gudang dinas sampai pustu, dalam satu alur.
                </h1>
                <ul class="mt-8 space-y-4 text-sm text-white/85">
                    <li class="flex items-start gap-3">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-accent-300" />
                        Stok per batch dengan FEFO/FIFO/LIFO
                    </li>
                    <li class="flex items-start gap-3">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-accent-300" />
                        Penerimaan, distribusi, permintaan, retur, dan opname
                    </li>
                    <li class="flex items-start gap-3">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-accent-300" />
                        LPLPO, RKO, neraca tahunan, dan tutup buku
                    </li>
                    <li class="flex items-start gap-3">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-accent-300" />
                        Prediksi kebutuhan obat berbasis data pemakaian
                    </li>
                </ul>
            </div>

            <div class="relative z-10 p-12 text-xs text-white/60">
                &copy; {{ now()->year }} Ruang Obat
            </div>
        </div>

        {{-- Form --}}
        <div class="flex w-full items-center justify-center px-4 py-12 sm:px-8 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-600 text-xs font-bold text-white">
                            RO
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">RUANG OBAT</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Dinas Kesehatan Kab. Kupang</p>
                        </div>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Masuk ke akun Anda</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Gunakan email dan password yang diberikan administrator.
                </p>

                @if (session('error'))
                    <div class="mt-6 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                        <x-heroicon-o-exclamation-circle class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400">
                                <x-heroicon-o-envelope class="h-5 w-5" />
                            </span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                @class([
                                    'w-full rounded-xl border bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-2 dark:bg-slate-900 dark:text-white',
                                    'border-red-300 ring-red-100 focus:border-red-400 focus:ring-red-100 dark:border-red-500/40' => $errors->has('email'),
                                    'border-slate-300 focus:border-brand-500 focus:ring-brand-100 dark:border-slate-700 dark:focus:border-brand-500 dark:focus:ring-brand-500/20' => ! $errors->has('email'),
                                ])
                                placeholder="nama@instansi.go.id"
                            >
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
                        <div class="relative" x-data="{ show: false }">
                            <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400">
                                <x-heroicon-o-lock-closed class="h-5 w-5" />
                            </span>
                            <input
                                id="password"
                                name="password"
                                :type="show ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                @class([
                                    'w-full rounded-xl border bg-white py-2.5 pl-11 pr-11 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-2 dark:bg-slate-900 dark:text-white',
                                    'border-red-300 ring-red-100 focus:border-red-400 focus:ring-red-100 dark:border-red-500/40' => $errors->has('password'),
                                    'border-slate-300 focus:border-brand-500 focus:ring-brand-100 dark:border-slate-700 dark:focus:border-brand-500 dark:focus:ring-brand-500/20' => ! $errors->has('password'),
                                ])
                                placeholder="••••••••"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-400 hover:text-slate-600"
                                @click="show = ! show"
                                tabindex="-1"
                            >
                                <x-heroicon-o-eye class="h-5 w-5" x-show="! show" />
                                <x-heroicon-o-eye-slash class="h-5 w-5" x-show="show" x-cloak />
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"
                        >
                        Ingat saya di perangkat ini
                    </label>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        <x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" />
                        Masuk
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>Butuh bantuan? Hubungi admin gudang farmasi.</span>
                    <button type="button" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800" onclick="
                        const dark = !document.documentElement.classList.contains('dark');
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.setItem('theme', dark ? 'dark' : 'light');
                    ">
                        <x-heroicon-o-moon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
