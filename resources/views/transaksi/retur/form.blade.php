@php
    $isEdit = $retur !== null;
    $header = [
        'tipe_retur' => old('tipe_retur', $retur->tipe_retur ?? $defaultTipe),
        'tanggal_retur' => old('tanggal_retur', $retur?->tanggal_retur?->format('Y-m-d') ?? now()->format('Y-m-d')),
        'supplier_id' => old('supplier_id', $retur->supplier_id ?? ''),
        'fasilitas_penerima_id' => old('fasilitas_penerima_id', $retur->fasilitas_penerima_id ?? $defaultPenerima),
        'alasan' => old('alasan', $retur->alasan ?? 'expired'),
        'alasan_lainnya' => old('alasan_lainnya', $retur->alasan_lainnya ?? ''),
        'catatan' => old('catatan', $retur->catatan ?? ''),
    ];
    $rows = old('rows', $oldRows);
    $tipeLabels = ['pustu_ke_puskesmas' => 'Pustu → Puskesmas', 'puskesmas_ke_gudang' => 'Puskesmas → Gudang', 'gudang_ke_supplier' => 'Gudang → Supplier'];
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Retur '.$retur->nomor_retur : 'Retur Baru')
@section('subtitle', 'Retur antar faskes & ke supplier')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            batchUrl: '{{ route('api.batch') }}',
            fasilitasId: @js(old('fasilitas_id', $defaultPengirim)),
            initialRows: @js($rows),
            withBatch: true,
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('retur.show', $retur) : route('retur.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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
            action="{{ $isEdit ? route('retur.update', $retur) : route('retur.store') }}"
            enctype="multipart/form-data"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header retur</h2>
                    @if ($lockedPengirim)
                        <p class="mt-1 text-xs text-slate-500">Pengirim: {{ $faskesList->firstWhere('id', $defaultPengirim)?->nama }}</p>
                    @endif
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tipe_retur" class="form-label">Tipe *</label>
                        @if ($lockedPengirim)
                            <input type="text" value="{{ $tipeLabels[$defaultTipe] ?? $defaultTipe }}" disabled class="form-input">
                            <input type="hidden" name="tipe_retur" value="{{ $defaultTipe }}">
                        @else
                            <select id="tipe_retur" name="tipe_retur" class="form-select">
                                @foreach ($tipeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($header['tipe_retur'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('tipe_retur')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_retur" class="form-label">Tanggal *</label>
                        <input id="tanggal_retur" name="tanggal_retur" type="date" value="{{ $header['tanggal_retur'] }}" required class="form-input">
                        @error('tanggal_retur')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="!['gudang_ke_supplier'].includes(document.getElementById('tipe_retur')?.value ?? '{{ $header['tipe_retur'] }}')">
                        <label for="fasilitas_penerima_id" class="form-label">Penerima</label>
                        <select id="fasilitas_penerima_id" name="fasilitas_penerima_id" class="form-select">
                            <option value="">— Otomatis / Gudang —</option>
                            @foreach ($faskesList as $f)
                                <option value="{{ $f->id }}" @selected((string) $header['fasilitas_penerima_id'] === (string) $f->id)>{{ $f->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="supplier_id" class="form-label">Supplier (khusus retur ke supplier)</label>
                        <select id="supplier_id" name="supplier_id" class="form-select">
                            <option value="">— Pilih supplier —</option>
                            @foreach ($supplierList as $s)
                                <option value="{{ $s->id }}" @selected((string) $header['supplier_id'] === (string) $s->id)>{{ $s->nama }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="alasan" class="form-label">Alasan *</label>
                        <select id="alasan" name="alasan" class="form-select">
                            @foreach (['expired' => 'Expired', 'rusak' => 'Rusak', 'kelebihan_stok' => 'Kelebihan stok', 'salah_kirim' => 'Salah kirim', 'recall' => 'Recall', 'near_expiry' => 'Near expiry', 'lainnya' => 'Lainnya'] as $value => $label)
                                <option value="{{ $value }}" @selected($header['alasan'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('alasan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="alasan_lainnya" class="form-label">Jelaskan alasan lain (wajib bila alasan = Lainnya)</label>
                        <input id="alasan_lainnya" name="alasan_lainnya" type="text" value="{{ $header['alasan_lainnya'] }}" class="form-input">
                        @error('alasan_lainnya')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ $header['catatan'] }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item retur</h2>
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

                            <div class="sm:col-span-3">
                                <label class="form-label" :for="'batch-' + i">Batch *</label>
                                <select :id="'batch-' + i" :name="'rows[' + i + '][batch_id]'" x-model="row.batch_id" @change="onBatchChange(i)" required class="form-select" data-tomselect-off>
                                    <option value="">— Pilih batch —</option>
                                    <template x-for="b in row.batches" :key="b.value">
                                        <option :value="b.value" x-text="b.label"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-slate-400" x-show="row.stok_sistem">Stok batch: <span x-text="row.stok_sistem"></span></p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'jumlah-' + i">Jumlah *</label>
                                <input :id="'jumlah-' + i" type="number" min="1" :name="'rows[' + i + '][jumlah_retur]'" x-model.number="row.jumlah" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'bukti-' + i">Bukti foto</label>
                                <input :id="'bukti-' + i" type="file" :name="'rows[' + i + '][bukti]'" accept="image/*" class="form-file">
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
            </section>

            <section class="flex items-center justify-end gap-2">
                <a href="{{ $isEdit ? route('retur.show', $retur) : route('retur.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan draft</button>
            </section>
        </form>
    </div>
@endsection
