/**
 * Form transaksi multi-baris (penerimaan & permintaan).
 *
 * - rows: [{ obat_id, obat_label, jumlah, harga_satuan, batch_number,
 *             tanggal_expired, keterangan, batches: [], obatOpen, obatLoading }]
 * - Penerimaan: obat dicari via fetch; no batch otomatis/manual + expired
 *   diisi manual (barang baru masuk).
 * - Permintaan: hanya obat + jumlah diminta + catatan.
 * - subtotal(i) dan grandTotal dihitung di klien untuk tampilan;
 *   server menghitung ulang saat simpan.
 *
 * Struktur mirip opnameForm (docForm.js) agar konsisten.
 */
export default ({
    obatUrl,
    batchUrl = null,
    stokUrl = null,
    fasilitasId = null,
    initialRows = [],
    withBatch = false,
    withPrice = false,
} = {}) => ({
    fasilitasId,
    withBatch,
    withPrice,
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

    async init() {
        for (let i = 0; i < this.rows.length; i++) {
            const row = this.rows[i];
            if (!row.obat_id || !this.withBatch) continue;
            await this.loadBatches(i);
            if (row.batch_id && !(row.batches || []).some((b) => String(b.value) === String(row.batch_id)) {
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
        batch_id: '',
        batch_number: '',
        tanggal_expired: '',
        jumlah: 0,
        harga_satuan: '',
        keterangan: '',
    };
}
