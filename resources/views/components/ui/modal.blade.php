@props(['name', 'title' => null, 'wide' => false])

<div
    x-show="{{ $name }}Open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    @keydown.escape.window="{{ $name }}Open = false"
    role="dialog"
    aria-modal="true"
>
    <div
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
        x-show="{{ $name }}Open"
        x-transition.opacity
        @click="{{ $name }}Open = false"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="{{ $name }}Open"
            x-transition.scale.95
            @click.stop
            class="relative w-full {{ $wide ? 'max-w-3xl' : 'max-w-lg' }} overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                @if ($title)
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                @else
                    <span></span>
                @endif
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    @click="{{ $name }}Open = false"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="px-5 py-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
