{{-- Pagination untuk tabel Alpine server-backed (tanpa reload). Dipakai di dalam x-data="dataTable". --}}
<div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row dark:border-slate-800">
    <div class="flex items-center gap-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            <span x-show="meta.total > 0">
                Menampilkan <span x-text="meta.from ?? 0"></span>–<span x-text="meta.to ?? 0"></span>
                dari <span x-text="Number(meta.total).toLocaleString('id-ID')"></span> data
            </span>
            <span x-show="meta.total === 0">Tidak ada data</span>
        </p>
        <select
            class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-950"
            :value="meta.per_page"
            @change="setPerPage($event.target.value)"
            title="Baris per halaman"
        >
            <template x-for="n in [10, 25, 50, 100]" :key="n">
                <option :value="n" x-text="n"></option>
            </template>
        </select>
    </div>

    <div class="flex items-center gap-1" x-show="meta.last_page > 1">
        <button
            type="button"
            class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800"
            :disabled="meta.page <= 1 || loading"
            @click="setPage(meta.page - 1)"
        >
            &lsaquo;
        </button>

        <template x-for="p in pageNumbers()" :key="p">
            <button
                type="button"
                class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-medium transition"
                :class="p === meta.page ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                :disabled="loading"
                @click="setPage(p)"
                x-text="p"
            ></button>
        </template>

        <button
            type="button"
            class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800"
            :disabled="meta.page >= meta.last_page || loading"
            @click="setPage(meta.page + 1)"
        >
            &rsaquo;
        </button>
    </div>
</div>
