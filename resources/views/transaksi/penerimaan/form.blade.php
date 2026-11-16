@php
    $isEdit = $penerimaan !== null;
    $header = [
        'tipe' => old('tipe', $penerimaan->tipe ?? 'pembelian'),
        'tanggal_penerimaan' => old('tanggal_penerimaan', $penerimaan?->tanggal_penerimaan?->format('Y-m-d') ?? now()->format('Y-m-d')),
        'supplier_id' => old('supplier_id', $penerimaan->supplier_id ?? ''),
        'sumber_dana_id' => old('sumber_dana_id', $penerimaan->sumber_dana_id ?? ''),
        'nomor_po' => old('nomor_po', $penerimaan->nomor_po ?? ''),
        'nomor_invoice' => old('nomor_invoice', $penerimaan->nomor_invoice ?? ''),
        'catatan' => old('catatan', $penerimaan->catatan ?? ''),
    ];
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Penerimaan '.$penerimaan->nomor_penerimaan : 'Penerimaan Baru')
@section('subtitle', 'Penerimaan stok dari supplier & hibah')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            fasilitasId: @js(old('fasilitas_id', $defaultFaskes)),
            initialRows: @js($rows),
            withPrice: true,
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('penerimaan.show', $penerimaan) : route('penerimaan.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali
            </a>
        </section>

        @if ($errors->any())
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <p class="font-medium">Periksa kembali isian berikut:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form
            method="POST"
            action="{{ $isEdit ? route('penerimaan.update', $penerimaan) : route('penerimaan.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header penerimaan</h2>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tipe" class="form-label">Tipe *</label>
                        <select id="tipe" name="tipe" class="form-select">
                            <option value="pembelian" @selected($header['tipe'] === 'pembelian')>Pembelian</option>
                            <option value="hibah" @selected($header['tipe'] === 'hibah')>Hibah</option>
                        </select>
                        @error('tipe')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_penerimaan" class="form-label">Tanggal *</label>
                        <input id="tanggal_penerimaan" name="tanggal_penerimaan" type="date" value="{{ $header['tanggal_penerimaan'] }}" required class="form-input">
                        @error('tanggal_penerimaan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="fasilitas_id" class="form-label">Lokasi</label>
                        @if ($lockedFaskes)
                            <input type="text" value="{{ $faskesList->firstWhere('id', $defaultFaskes)?->nama ?? 'Faskes saya' }}" disabled class="form-input">
                            <input type="hidden" name="fasilitas_id" value="{{ $defaultFaskes }}">
                        @else
                            <select id="fasilitas_id" name="fasilitas_id" x-model="fasilitasId" @change="onFaskesChange()" class="form-select">
                                <option value="">Gudang Dinas</option>
                                @foreach ($faskesList as $f)
                                    <option value="{{ $f->id }}">{{ $f->nama }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('fasilitas_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select">
                            <option value="">— Tanpa supplier —</option>
                            @foreach ($supplierList as $s)
                                <option value="{{ $s->id }}" @selected((string) $header['supplier_id'] === (string) $s->id)>{{ $s->nama }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="sumber_dana_id" class="form-label">Sumber dana</label>
                        <select id="sumber_dana_id" name="sumber_dana_id" class="form-select">
                            <option value="">— Tanpa sumber dana —</option>
                            @foreach ($danaList as $d)
                                <option value="{{ $d->id }}" @selected((string) $header['sumber_dana_id'] === (string) $d->id)>{{ $d->kode }} · {{ $d->nama }} ({{ $d->tahun }})</option>
                            @endforeach
                        </select>
                        @error('sumber_dana_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="nomor_po" class="form-label">No. PO</label>
                        <input id="nomor_po" name="nomor_po" type="text" value="{{ $header['nomor_po'] }}" class="form-input">
                    </div>

                    <div>
                        <label for="nomor_invoice" class="form-label">No. invoice</label>
                        <input id="nomor_invoice" name="nomor_invoice" type="text" value="{{ $header['nomor_invoice'] }}" class="form-input">
                    </div>

                    <div>
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ $header['catatan'] }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item penerimaan</h2>
                    <button type="button" class="btn btn-secondary btn-sm" @click="addRow()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah baris
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-12">
                            <div class="relative sm:col-span-4">
                                <label class="form-label" :for="'obat-search-' + i">Obat *</label>
                                <input
                                    :id="'obat-search-' + i"
                                    type="text"
                                    placeholder="Ketik untuk mencari…"
                                    :value="row.obat_label"
                                    @input="row.obat_label = $event.target.value; row.obatOpen = true; searchObat(i, $event.target.value)"
                                    @focus="row.obatOpen = true; if (!row.obatResults.length) searchObat(i, '')"
                                    @click.outside="row.obatOpen = false"
                                    class="form-input"
                                    autocomplete="off"
                                >
                                <input type="hidden" :name="'rows[' + i + '][obat_id]'" x-model="row.obat_id">
                                <div x-show="row.obatOpen && row.obatResults.length" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                    <template x-for="item in row.obatResults" :key="item.value">
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="pickObat(i, item)">
                                            <span class="block font-medium text-slate-800 dark:text-slate-100" x-text="item.label"></span>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-1 flex items-center justify-between">
                                    <span class="text-xs text-slate-400" x-show="row.obatLoading">Mencari…</span>
                                    <button type="button" class="text-xs text-slate-400 hover:text-slate-600" x-show="row.obat_id" @click="clearObat(i)">Ganti</button>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'batch-' + i">No. batch *</label>
                                <input :id="'batch-' + i" type="text" :name="'rows[' + i + '][batch_number]'" x-model="row.batch_number" required class="form-input font-mono text-xs">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'expired-' + i">Kedaluwarsa *</label>
                                <input :id="'expired-' + i" type="date" :name="'rows[' + i + '][tanggal_expired]'" x-model="row.tanggal_expired" required class="form-input">
                            </div>

                            <div class="sm:col-span-1">
                                <label class="form-label" :for="'jumlah-' + i">Jumlah *</label>
                                <input :id="'jumlah-' + i" type="number" min="1" :name="'rows[' + i + '][jumlah]'" x-model.number="row.jumlah" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'harga-' + i">Harga satuan</label>
                                <input :id="'harga-' + i" type="number" min="0" step="0.01" :name="'rows[' + i + '][harga_satuan]'" x-model="row.harga_satuan" class="form-input text-right">
                                <p class="mt-1 text-right text-xs font-medium text-slate-600 dark:text-slate-300" x-text="fmtRupiah(subtotal(row))"></p>
                            </div>

                            <div class="flex items-end justify-end sm:col-span-1">
                                <button type="button" class="btn-icon btn-icon-danger" title="Hapus baris" @click="removeRow(i)">
                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>

                    @error('rows')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Total biaya</p>
                    <p class="text-lg font-semibold text-slate-900 dark:text-white" x-text="fmtRupiah(grandTotal)"></p>
                </div>
            </section>

            <section class="flex items-center justify-end gap-2">
                <a href="{{ $isEdit ? route('penerimaan.show', $penerimaan) : route('penerimaan.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan draft</button>
            </section>
        </form>
    </div>
@endsection
