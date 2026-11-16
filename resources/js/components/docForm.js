/**
 * Form multi-baris untuk stok opname.
 *
 * - rows: [{ obat_id, obat_label, batch_id, batch_number, tanggal_expired,
 *             stok_sistem, stok_fisik, batches: [], obatOpen: bool, obatLoading: bool }]
 * - Obat dicari via fetch (TomSelect tidak dipakai di baris dinamis).
 * - Batch diambil via fetch per obat + faskes, stok sistem terisi otomatis.
 * - Selisih hanya tampilan; server menghitung ulang saat simpan.
 */
export default ({
    obatUrl,
    batchUrl,
    stokUrl,
    fasilitasId = null,
    tipe = 'penyesuaian',
    initialRows = [],
} = {}) => ({
    tipe,
    fasilitasId,
    rows: initialRows.length ? initialRows.map((r) => ({ ...emptyRow(), ...r })) : [emptyRow()],
    obatQuery: '',

    async init() {
        for (let i = 0; i < this.rows.length; i++) {
            const row = this.rows[i];
            if (!row.obat_id) continue;
            await this.loadBatches(i);
            if (row.batch_id && !(row.batches || []).some((b) => String(b.value) === String(row.batch_id))) {
                row.batches.unshift({
                    value: row.batch_id,
                    label: `${row.batch_number || '-'} (saat opname)`,
                });
            }
        }
    },

    addRow() {
        this.rows.push(emptyRow());
    },

    removeRow(i) {
        if (this.rows.length <= 1) {
            this.rows[0] = emptyRow();
            return;
        }
        this.rows.splice(i, 1);
    },

    selisih(row) {
        const fisik = Number(row.stok_fisik) || 0;
        if (this.tipe === 'penyesuaian') {
            return fisik - (Number(row.stok_sistem) || 0);
        }
        return fisik;
    },

    async searchObat(i, q) {
        const row = this.rows[i];
        row.obatLoading = true;
        try {
            const res = await fetch(`${obatUrl}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            row.obatResults = res.ok ? await res.json() : [];
        } catch {
            row.obatResults = [];
        } finally {
            row.obatLoading = false;
        }
    },

    async pickObat(i, item) {
        const row = this.rows[i];
        row.obat_id = item.value;
        row.obat_label = item.label;
        row.obatOpen = false;
        row.batch_id = '';
        row.batches = [];
        await this.loadBatches(i);
        await this.loadStokSistem(i);
    },

    clearObat(i) {
        const row = this.rows[i];
        Object.assign(row, emptyRow());
    },

    async onFaskesChange() {
        for (let i = 0; i < this.rows.length; i++) {
            const row = this.rows[i];
            if (row.obat_id) {
                await this.loadBatches(i);
                await this.loadStokSistem(i);
            }
        }
    },

    async loadBatches(i) {
        const row = this.rows[i];
        if (!row.obat_id) {
            row.batches = [];
            return;
        }
        const params = new URLSearchParams({ obat_id: row.obat_id });
        if (this.fasilitasId) params.set('fasilitas_id', this.fasilitasId);
        try {
            const res = await fetch(`${batchUrl}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            row.batches = res.ok ? await res.json() : [];
        } catch {
            row.batches = [];
        }
    },

    onBatchChange(i) {
        const row = this.rows[i];
        const found = (row.batches || []).find((b) => String(b.value) === String(row.batch_id));
        if (found) {
            row.batch_number = found.batch_number;
            row.tanggal_expired = found.tanggal_expired;
            row.stok_sistem = found.jumlah;
        } else {
            row.stok_sistem = 0;
        }
    },

    async loadStokSistem(i) {
        const row = this.rows[i];
        if (!row.obat_id || row.batch_id) return;
        const params = new URLSearchParams({ obat_id: row.obat_id });
        if (this.fasilitasId) params.set('fasilitas_id', this.fasilitasId);
        try {
            const res = await fetch(`${stokUrl}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const json = await res.json();
                row.stok_sistem = json.jumlah ?? 0;
            }
        } catch {
            /* biarkan 0 */
        }
    },
});

function emptyRow() {
    return {
        obat_id: '',
        obat_label: '',
        obatOpen: false,
        obatLoading: false,
        obatResults: [],
        batch_id: '',
        batches: [],
        batch_number: '',
        tanggal_expired: '',
        stok_sistem: 0,
        stok_fisik: 0,
    };
}
