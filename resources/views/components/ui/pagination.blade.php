@props(['paginator'])

@if ($paginator->hasPages())
    <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ number_format($paginator->total(), 0, ',', '.') }} data
        </p>
        <div class="flex items-center gap-1">
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if ($page === 1 || $page === $paginator->lastPage() || abs($page - $paginator->currentPage()) <= 2)
                    <a
                        href="{{ $url }}"
                        @class([
                            'grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-medium transition',
                            'bg-brand-600 text-white shadow-sm' => $page === $paginator->currentPage(),
                            'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => $page !== $paginator->currentPage(),
                        ])
                    >
                        {{ $page }}
                    </a>
                @elseif (abs($page - $paginator->currentPage()) === 3)
                    <span class="px-1 text-xs text-slate-400">…</span>
                @endif
            @endforeach
        </div>
    </div>
@endif
