@php
    $isEdit = $opname !== null;
    $header = [
        'tipe' => old('tipe', $opname->tipe ?? 'penyesuaian'),
        'tanggal_opname' => old('tanggal_opname', $opname?->tanggal_opname?->format('Y-m-d') ?? now()->format('Y-m-d')),
        'status' => old('status', $opname->status ?? 'draft'),
        'catatan' => old('catatan', $opname->catatan ?? ''),
    ];
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Opname '.$opname->nomor_opname : 'Opname Baru')
@section('subtitle', 'Perhitungan stok fisik vs sistem')

@section('content')
    <div
        class="space-y-6"
        x-data="opnameForm({
            obatUrl: '{{ route('api.obat.search') }}',
            batchUrl: '{{ route('api.batch') }}',
            stokUrl: '{{ route('api.stok-sistem') }}',
            fasilitasId: @js(old('fasilitas_id', $defaultFaskes)),
            tipe: @js($header['tipe']),
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('opname.show', $opname) : route('opname.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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
            action="{{ $isEdit ? route('opname.update', $opname) : route('opname.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header opname</h2>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tipe" class="form-label">Tipe *</label>
                        <select id="tipe" name="tipe" x-model="tipe" class="form-select" @if ($isEdit) disabled @endif>
                            <option value="penyesuaian">Penyesuaian (stok existing)</option>
                            <option value="stok_awal">Stok awal</option>
                            <option value="stok_baru">Stok baru</option>
                        </select>
                        @if ($isEdit)
                            <input type="hidden" name="tipe" :value="tipe">
                        @endif
                        @error('tipe')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_opname" class="form-label">Tanggal *</label>
                        <input id="tanggal_opname" name="tanggal_opname" type="date" value="{{ $header['tanggal_opname'] }}" required class="form-input">
                        @error('tanggal_opname')<p class="form-error">{{ $message }}</p>@enderror
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
                        <label for="status" class="form-label">Simpan sebagai *</label>
                        <select id="status" name="status" class="form-select">
                            @foreach (['draft' => 'Draft', 'proses' => 'Proses', 'selesai' => 'Selesai (terapkan ke stok)'] as $value => $label)
                                <option value="{{ $value }}" @selected($header['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ $header['catatan'] }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item opname</h2>
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

                            <div class="sm:col-span-3" x-show="tipe === 'penyesuaian'">
                                <label class="form-label" :for="'batch-' + i">Batch</label>
                                <select :id="'batch-' + i" :name="'rows[' + i + '][batch_id]'" x-model="row.batch_id" @change="onBatchChange(i)" class="form-select" data-tomselect-off>
                                    <option value="">— Tanpa batch —</option>
                                    <template x-for="b in row.batches" :key="b.value">
                                        <option :value="b.value" x-text="b.label"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="sm:col-span-2" x-show="tipe !== 'penyesuaian'">
                                <label class="form-label" :for="'batch-number-' + i">No. batch</label>
                                <input :id="'batch-number-' + i" type="text" :name="'rows[' + i + '][batch_number]'" x-model="row.batch_number" placeholder="Otomatis bila kosong" class="form-input font-mono text-xs">
                            </div>

                            <div class="sm:col-span-2" x-show="tipe !== 'penyesuaian'">
                                <label class="form-label" :for="'expired-' + i">Kedaluwarsa *</label>
                                <input :id="'expired-' + i" type="date" :name="'rows[' + i + '][tanggal_expired]'" x-model="row.tanggal_expired" class="form-input">
                            </div>

                            <div class="sm:col-span-2" x-show="tipe === 'penyesuaian'">
                                <label class="form-label">Stok sistem</label>
                                <input type="text" :value="row.stok_sistem" disabled class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'fisik-' + i">Stok fisik *</label>
                                <input :id="'fisik-' + i" type="number" min="0" :name="'rows[' + i + '][stok_fisik]'" x-model.number="row.stok_fisik" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label">Selisih</label>
                                <p class="rounded-xl bg-slate-50 px-3 py-2 text-right text-sm font-semibold dark:bg-slate-800"
                                   :class="selisih(row) === 0 ? 'text-slate-500' : (selisih(row) > 0 ? 'text-emerald-600' : 'text-red-600')"
                                   x-text="(selisih(row) > 0 ? '+' : '') + selisih(row)"></p>
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
                <a href="{{ $isEdit ? route('opname.show', $opname) : route('opname.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan opname</button>
            </section>
        </form>
    </div>
@endsection
