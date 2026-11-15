<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &mdash; {{ config('app.name') }}</title>
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
@php
    $user = auth()->user();
    $initial = (bool) ($prefs->sidebar_collapsed ?? false);
    $posisi = $prefs->posisi_navbar ?? 'sidebar';
@endphp
<div
    x-data
    x-init="$store.ui.sidebarOpen = window.innerWidth >= 1024 ? {{ $initial ? 'false' : 'true' }} : false"
    class="min-h-screen"
>
    @if ($posisi === 'sidebar')
    {{-- Overlay mobile --}}
    <div
        x-show="$store.ui.mobileOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm lg:hidden"
        @click="$store.ui.mobileOpen = false"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-slate-200 bg-white transition-all duration-200 dark:border-slate-800 dark:bg-slate-900"
        :class="[
            $store.ui.sidebarOpen ? 'lg:w-64' : 'lg:w-20',
            $store.ui.mobileOpen ? 'w-64 translate-x-0' : '-translate-x-full lg:translate-x-0',
        ]"
    >
        <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-600 text-sm font-bold text-white shadow-sm">
                    RO
                </span>
                <span
                    class="truncate text-sm font-semibold tracking-tight text-slate-900 dark:text-white"
                    x-show="$store.ui.sidebarOpen"
                >
                    Ruang Obat
                </span>
            </a>

            <button
                type="button"
                class="ml-auto hidden rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:block dark:hover:bg-slate-800 dark:hover:text-slate-200"
                @click="$store.ui.toggleSidebar()"
                :title="$store.ui.sidebarOpen ? 'Ciutkan menu' : 'Lebarkan menu'"
            >
                <x-heroicon-o-chevron-double-left class="h-4 w-4" x-show="$store.ui.sidebarOpen" />
                <x-heroicon-o-chevron-double-right class="h-4 w-4" x-show="! $store.ui.sidebarOpen" x-cloak />
            </button>
        </div>

        <nav class="scrollbar-thin flex-1 space-y-6 overflow-y-auto px-3 py-4">
            @foreach ($navSections as $section)
                <div>
                    @if ($section['label'])
                        <p
                            class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500"
                            x-show="$store.ui.sidebarOpen"
                            x-cloak
                        >
                            {{ $section['label'] }}
                        </p>
                    @endif

                    <ul class="space-y-1">
                        @foreach ($section['items'] as $item)
                            @php $active = request()->routeIs($item['route']); @endphp
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition',
                                        'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $active,
                                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
                                    ])
                                    :title="! $store.ui.sidebarOpen ? '{{ $item['label'] }}' : null"
                                >
                                    <x-dynamic-component
                                        :component="'heroicon-o-' . $item['icon']"
                                        class="h-5 w-5 shrink-0"
                                    />
                                    <span class="truncate" x-show="$store.ui.sidebarOpen" x-cloak>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-slate-200 p-3 dark:border-slate-800">
            <p class="px-2 text-[11px] text-slate-400 dark:text-slate-500" x-show="$store.ui.sidebarOpen" x-cloak>
                v0.1 &middot; {{ now()->translatedFormat('M Y') }}
            </p>
        </div>
    </aside>
    @endif

    <div class="flex min-h-screen flex-col transition-all duration-200" :class="$posisi === 'topbar' ? 'lg:pl-0' : ($store.ui.sidebarOpen ? 'lg:pl-64' : 'lg:pl-20')">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                @if ($posisi === 'sidebar')
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden dark:hover:bg-slate-800"
                    @click="$store.ui.toggleSidebar()"
                >
                    <x-heroicon-o-bars-3 class="h-5 w-5" />
                </button>
                @endif

                <div class="min-w-0">
                    <h1 class="truncate text-base font-semibold text-slate-900 sm:text-lg dark:text-white">
                        @yield('title', 'Dashboard')
                    </h1>
                    <p class="hidden truncate text-xs text-slate-500 sm:block dark:text-slate-400">
                        @yield('subtitle', 'Sistem manajemen stok obat Dinas Kesehatan')
                    </p>
                </div>

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        @click="$store.ui.toggleDark()"
                        :title="$store.ui.dark ? 'Mode terang' : 'Mode gelap'"
                    >
                        <x-heroicon-o-sun class="h-5 w-5" x-show="$store.ui.dark" x-cloak />
                        <x-heroicon-o-moon class="h-5 w-5" x-show="! $store.ui.dark" />
                    </button>

                    <div class="relative" x-data="dropdown">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl py-1 pl-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800"
                            @click="toggle()"
                            @keydown.escape.window="close()"
                        >
                            @if ($avatar = $user->avatarUrl())
                                <img src="{{ $avatar }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-xl object-cover">
                            @else
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/10 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-200">
                                    {{ $user->initials() }}
                                </span>
                            @endif

                            <span class="hidden text-left sm:block">
                                <span class="block max-w-[10rem] truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {{ $user->name }}
                                </span>
                                <span class="block max-w-[10rem] truncate text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $user->getRoleNames()->map(fn ($r) => str($r)->replace('_', ' ')->title())->implode(', ') ?: 'Tanpa role' }}
                                </span>
                            </span>

                            <x-heroicon-o-chevron-down class="h-4 w-4 text-slate-400" />
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition.origin.top.right
                            @click.outside="close()"
                            class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                            </div>

                            <a href="{{ route('profil.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                <x-heroicon-o-user-circle class="h-4 w-4" />
                                Profil &amp; preferensi
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                    <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if ($posisi === 'topbar')
                <nav class="hidden border-t border-slate-200 lg:block dark:border-slate-800">
                    <div class="flex items-center gap-1 overflow-x-auto px-4 py-1.5">
                        @foreach (collect($navSections)->flatMap(fn ($section) => $section['items']) as $item)
                            @php $active = request()->routeIs($item['route']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                @class([
                                    'flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                    'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $active,
                                    'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! $active,
                                ])
                            >
                                <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="h-4 w-4" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 px-4 py-4 text-xs text-slate-500 sm:px-6 lg:px-8 dark:border-slate-800 dark:text-slate-400">
            &copy; {{ now()->year }} Ruang Obat &middot; Dinas Kesehatan Kabupaten Kupang
        </footer>
    </div>

    {{-- Toast --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
        <template x-for="toast in $store.toast.items" :key="toast.id">
            <div
                x-transition
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg"
                :class="toast.type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'"
            >
                <span class="mt-0.5">
                    <x-heroicon-o-check-circle class="h-5 w-5" x-show="toast.type !== 'error'" />
                    <x-heroicon-o-exclamation-circle class="h-5 w-5" x-show="toast.type === 'error'" x-cloak />
                </span>
                <p class="flex-1 text-sm" x-text="toast.message"></p>
                <button type="button" class="text-current/70 hover:text-current" @click="$store.toast.dismiss(toast.id)">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </template>
    </div>

    @if (session('success'))
        <div x-init="$store.toast.push(@js(session('success')), 'success')"></div>
    @endif
    @if (session('error'))
        <div x-init="$store.toast.push(@js(session('error')), 'error')"></div>
    @endif
    @if (session('status'))
        <div x-init="$store.toast.push(@js(session('status')), 'success')"></div>
    @endif
</div>
</body>
</html>
