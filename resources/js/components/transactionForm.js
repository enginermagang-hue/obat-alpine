/**
 * Form transaksi multi-baris (penerimaan, permintaan, distribusi).
 *
 * - rows: [{ obat_id, obat_label, jumlah, harga_satuan, batch_number,
 *             tanggal_expired, keterangan, batches: [], alokasi: [],
 *             obatOpen, obatLoading }]
 * - Penerimaan: obat dicari via fetch; no batch + expired diisi manual.
 * - Permintaan: hanya obat + jumlah diminta + catatan.
 * - Distribusi: obat + jumlah; batch dialokasikan server (FEFO/FIFO/LIFO),
 *   preview via loadAlokasi().
 * - subtotal(i) dan grandTotal dihitung di klien untuk tampilan;
 *   server menghitung ulang saat simpan.
 */
export default ({
    obatUrl,
    batchUrl = null,
    stokUrl = null,
    alokasiUrl = null,
    fasilitasId = null,
    initialRows = [],
    withBatch = false,
    withPrice = false,
} = {}) => ({
    fasilitasId,
    withBatch,
    withPrice,
    alokasiUrl,
    alokasiLoading: false,
    rows: initialRows.length ? initialRows.map((r) => ({ ...emptyRow(), ...r })) : [emptyRow()],

    addRow() {
        this.rows.push(emptyRow());
    },

    removeRow(i) {
        if (this.rows.length <= 1) {
            this.rows = [emptyRow()];
            return;
        }
        this.rows.splice(i, 1);
    },

    subtotal(row) {
        return (Number(row.jumlah) || 0) * (Number(row.harga_satuan) || 0);
    },

    get grandTotal() {
        return this.rows.reduce((s, r) => s + this.subtotal(r), 0);
    },

    fmtRupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
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
        if (this.withBatch) {
            await this.loadBatches(i);
        }
    },

    clearObat(i) {
        Object.assign(this.rows[i], emptyRow());
    },

    async onFaskesChange() {
        for (let i = 0; i < this.rows.length; i++) {
            const row = this.rows[i];
            if (row.obat_id) {
                await this.loadBatches(i);
            }
        }
    },

    async loadBatches(i) {
        const row = this.rows[i];
        if (!batchUrl || !row.obat_id) {
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

    /**
     * Muat preview alokasi batch per baris (dipakai form distribusi).
     * Server mengalokasikan ulang saat submit; ini hanya tampilan.
     */
    async loadAlokasi() {
        if (!this.alokasiUrl) return;
        this.alokasiLoading = true;
        try {
            for (let i = 0; i < this.rows.length; i++) {
                const row = this.rows[i];
                row.alokasi = [];
                if (!row.obat_id || !(Number(row.jumlah) > 0)) continue;
                const params = new URLSearchParams({ obat_id: row.obat_id, jumlah: row.jumlah });
                if (this.fasilitasId) params.set('fasilitas_id', this.fasilitasId);
                const res = await fetch(`${this.alokasiUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                row.alokasi = res.ok ? await res.json() : [];
            }
        } finally {
            this.alokasiLoading = false;
        }
    },

    async init() {
        // Normalisasi alias antar modul ke bentuk baku
        // { obat_id, jumlah, keterangan, batch_*, harga_satuan, ... }.
        for (const row of this.rows) {
            if (row.jumlah_diminta !== undefined && (row.jumlah === undefined || row.jumlah === 0)) {
                row.jumlah = row.jumlah_diminta;
            }
            if (row.catatan !== undefined && (row.keterangan === undefined || row.keterangan === '')) {
                row.keterangan = row.catatan;
            }
        }
        for (let i = 0; i < this.rows.length; i++) {
            const row = this.rows[i];
            if (!row.obat_id || !this.withBatch) continue;
            await this.loadBatches(i);
            if (row.batch_id && !(row.batches || []).some((b) => String(b.value) === String(row.batch_id))) {
                row.batches.unshift({
                    value: row.batch_id,
                    label: `${row.batch_number || '-'} (saat dokumen)`,
                });
            }
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
        batches: [],
        alokasi: [],
        batch_id: '',
        batch_number: '',
        tanggal_expired: '',
        jumlah: 0,
        harga_satuan: '',
        keterangan: '',
    };
}
