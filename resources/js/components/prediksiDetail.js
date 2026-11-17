export default () => ({
    detailOpen: false,
    detail: null,
    detailLoading: false,

    exportUrl(base) {
        const q = { ...this.filters };
        delete q.page;
        delete q.per_page;
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(q)) {
            if (v !== '' && v !== null && v !== undefined) params.set(k, v);
        }
        const qs = params.toString();
        return base + (qs ? '?' + qs : '');
    },

    async openDetail(obatId) {
        this.detailOpen = true;
        this.detail = null;
        this.detailLoading = true;
        try {
            const params = new URLSearchParams({ ...this.filters, obat_id: obatId });
            const res = await fetch(`${window.location.pathname}/${obatId}/detail?${params}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            this.detail = await res.json();
        } catch (e) {
            this.error = 'Gagal memuat detail. Coba lagi.';
        } finally {
            this.detailLoading = false;
        }
    },
});
