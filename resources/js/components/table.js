/**
 * Tabel server-backed untuk Alpine: filter, sort, dan pagination tanpa reload.
 *
 * Server tetap melakukan query (filter + sort + paginate di SQL) dan
 * mengembalikan JSON { data, meta }. Komponen ini hanya mengelola state
 * dan render; tidak ada data bisnis yang dihitung di browser.
 *
 * Pemakaian:
 *   <div x-data="dataTable({ url: '/obat', initial: {...}, filterKeys: [...] })">
 *     <form @submit.prevent="applyFilter()">
 *       <input x-model.debounce.300ms="filters.search">
 *       <select x-model="filters.status" @change="applyFilter()">
 *     </form>
 *     <button @click="setSort('nama')">…</button>
 *     <template x-for="row in rows" :key="row.id">…</template>
 *     <x-ui.table-pagination />
 *   </div>
 *
 * - initial: { data, meta, filters } dari render pertama (tanpa request ganda).
 * - AbortController membatalkan request lama saat mengetik cepat.
 * - state disinkronkan ke URL (replaceState) agar bookmark & back jalan.
 */
export default ({ url, initial = null, filterKeys = [], extra = {} } = {}) => ({
    url,
    filterKeys,
    filters: {},
    rows: [],
    meta: { page: 1, per_page: 10, total: 0, last_page: 1, from: null, to: null },
    sort: null,
    sortDir: 'asc',
    loading: false,
    error: null,
    abort: null,
    ...extra,

    init() {
        if (initial) {
            this.rows = initial.data ?? [];
            this.meta = { ...this.meta, ...(initial.meta ?? {}) };
            this.filters = { ...(initial.filters ?? {}) };
            this.sort = initial.sort ?? null;
            this.sortDir = initial.sortDir ?? 'asc';
        }

        const params = new URLSearchParams(window.location.search);
        for (const key of this.filterKeys) {
            if (params.has(key) && this.filters[key] === undefined) {
                this.filters[key] = params.get(key);
            }
        }
        if (params.has('page')) {
            const page = parseInt(params.get('page'), 10);
            if (Number.isInteger(page) && page > 1 && (page !== this.meta.page || this.rows.length === 0)) {
                this.load(page);
                return;
            }
        }
    },

    queryObject(page = 1) {
        const q = { page };
        for (const [key, value] of Object.entries(this.filters)) {
            if (value !== '' && value !== null && value !== undefined) {
                q[key] = value;
            }
        }
        if (this.sort) {
            q.sort = this.sort;
            q.sortDir = this.sortDir;
        }
        if (this.meta.per_page) {
            q.per_page = this.meta.per_page;
        }
        return q;
    },

    buildUrl(page = 1) {
        return `${this.url}?${new URLSearchParams(this.queryObject(page))}`;
    },

    exportUrl(baseUrl) {
        const q = this.queryObject(1);
        delete q.page;
        delete q.per_page;
        return `${baseUrl}?${new URLSearchParams(q)}`;
    },

    async load(page = 1) {
        if (this.abort) this.abort.abort();
        this.abort = new AbortController();
        this.loading = true;
        this.error = null;

        try {
            const res = await fetch(this.buildUrl(page), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.abort.signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            this.rows = json.data ?? [];
            this.meta = { ...this.meta, ...(json.meta ?? {}) };
            this.syncUrl(page);
        } catch (e) {
            if (e?.name !== 'AbortError') {
                this.error = 'Gagal memuat data. Coba lagi.';
            }
        } finally {
            this.loading = false;
        }
    },

    applyFilter() {
        this.load(1);
    },

    reset() {
        this.filters = {};
        this.sort = null;
        this.sortDir = 'asc';
        this.load(1);
    },

    setSort(column) {
        if (this.sort === column) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sort = column;
            this.sortDir = 'asc';
        }
        this.load(1);
    },

    setPerPage(n) {
        this.meta.per_page = parseInt(n, 10) || 10;
        this.load(1);
    },

    setPage(page) {
        const p = parseInt(page, 10);
        if (!Number.isInteger(p) || p < 1 || p > (this.meta.last_page || 1)) return;
        this.load(p);
    },

    syncUrl(page) {
        const params = new URLSearchParams(this.queryObject(page));
        const clean = new URLSearchParams();
        for (const [k, v] of params) {
            if (k === 'page' && String(v) === '1') continue;
            clean.set(k, v);
        }
        const qs = clean.toString();
        window.history.replaceState(null, '', `${window.location.pathname}${qs ? '?' + qs : ''}`);
    },

    pageNumbers() {
        const current = this.meta.page || 1;
        const last = this.meta.last_page || 1;
        const pages = [];
        for (let p = 1; p <= last; p++) {
            if (p === 1 || p === last || Math.abs(p - current) <= 2) pages.push(p);
        }
        return pages;
    },

    fmtNum(n) {
        if (n === null || n === undefined || n === '') return '—';
        return Number(n).toLocaleString('id-ID');
    },

    fmtRupiah(n) {
        if (n === null || n === undefined || n === '') return '—';
        return 'Rp ' + Number(n).toLocaleString('id-ID');
    },

    fmtDate(d) {
        if (!d) return '—';
        const dt = new Date(d.length === 10 ? d + 'T00:00:00' : d);
        return isNaN(dt) ? String(d) : dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    isPast(d) {
        if (!d) return false;
        const dt = new Date(d.length === 10 ? d + 'T00:00:00' : d);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return dt < today;
    },

    isNear(d, days = 90) {
        if (!d) return false;
        const dt = new Date(d.length === 10 ? d + 'T00:00:00' : d);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const limit = new Date(today);
        limit.setDate(limit.getDate() + days);
        return dt >= today && dt <= limit;
    },

    cap(s) {
        if (s === null || s === undefined || s === '') return '—';
        return String(s).charAt(0).toUpperCase() + String(s).slice(1);
    },
});
