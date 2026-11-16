@php
    $isEdit = $inspeksi !== null;
    $header = [
        'hasil_inspeksi' => old('hasil_inspeksi', $inspeksi->hasil_inspeksi ?? ''),
        'tindakan' => old('tindakan', $inspeksi->tindakan ?? ''),
        'catatan_inspeksi' => old('catatan_inspeksi', $inspeksi->catatan_inspeksi ?? ''),
        'tanggal_inspeksi' => old('tanggal_inspeksi', $inspeksi?->tanggal_inspeksi?->format('Y-m-d') ?? now()->format('Y-m-d')),
    ];
    $detailMap = $retur?->details->mapWithKeys(fn ($d) => [$d->id => ['batch_id' => $d->batch_id, 'label' => ($d->obat?->nama_obat ?? '').' — '.($d->batch?->batch_number ?? '').' ('.$d->jumlah_retur.')']])->toArray() ?? [];
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Inspeksi' : 'Inspeksi Baru')
@section('subtitle', 'Hasil inspeksi tidak mengubah stok')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            returId: @js(old('retur_id', $retur?->id)),
            detailId: @js(old('detail_retur_id', $oldDetail)),
            batchId: @js(old('batch_id', $inspeksi->batch_id ?? '')),
            detailMap: @js($detailMap),
            pickDetail(id) { this.detailId = id; const d = this.detailMap[id]; this.batchId = d ? d.batch_id : ''; },
        }"
    >
        <section>
            <a href="{{ $isEdit ? route('inspeksi-retur.show', $inspeksi) : route('inspeksi-retur.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            Inspeksi hanya mencatat hasil &amp; tindakan — <strong>tidak mengubah stok</strong>.
        </div>

        <form
            method="POST"
            action="{{ $isEdit ? route('inspeksi-retur.update', $inspeksi) : route('inspeksi-retur.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Hasil inspeksi</h2>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="retur_id" class="form-label">Retur *</label>
                        @if ($isEdit)
                            <input type="text" value="{{ $retur?->nomor_retur }}" disabled class="form-input">
                            <input type="hidden" name="retur_id" value="{{ $retur?->id }}">
                        @else
                            <select id="retur_id" name="retur_id" x-model="returId" @change="window.location = '{{ route('inspeksi-retur.create') }}?retur_id=' + $event.target.value" class="form-select">
                                <option value="">— Pilih retur (diterima/selesai) —</option>
                                @foreach ($returList as $r)
                                    <option value="{{ $r->id }}">{{ $r->nomor_retur }} ({{ ucfirst($r->status) }})</option>
                                @endforeach
                            </select>
                        @endif
                        @error('retur_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="detail_retur_id" class="form-label">Item retur *</label>
                        <select id="detail_retur_id" name="detail_retur_id" x-model="detailId" @change="pickDetail($event.target.value)" required class="form-select">
                            <option value="">— Pilih item —</option>
                            @foreach ($detailMap as $id => $d)
                                <option value="{{ $id }}" @selected((string) old('detail_retur_id', $oldDetail) === (string) $id)>{{ $d['label'] }}</option>
                            @endforeach
                        </select>
                        @error('detail_retur_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <input type="hidden" name="batch_id" :value="batchId">

                    <div>
                        <label for="hasil_inspeksi" class="form-label">Hasil inspeksi *</label>
                        <select id="hasil_inspeksi" name="hasil_inspeksi" class="form-select">
                            <option value="">— Pilih —</option>
                            <option value="layak" @selected($header['hasil_inspeksi'] === 'layak')>Layak</option>
                            <option value="tidak_layak" @selected($header['hasil_inspeksi'] === 'tidak_layak')>Tidak layak</option>
                            <option value="perlu_tindakan_lanjut" @selected($header['hasil_inspeksi'] === 'perlu_tindakan_lanjut')>Perlu tindak lanjut</option>
                        </select>
                        @error('hasil_inspeksi')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tindakan" class="form-label">Tindakan *</label>
                        <select id="tindakan" name="tindakan" class="form-select">
                            <option value="">— Pilih —</option>
                            <option value="didistribusi_kembali" @selected($header['tindakan'] === 'didistribusi_kembali')>Didistribusi kembali</option>
                            <option value="dimusnahkan" @selected($header['tindakan'] === 'dimusnahkan')>Dimusnahkan</option>
                            <option value="dikembalikan_ke_supplier" @selected($header['tindakan'] === 'dikembalikan_ke_supplier')>Dikembalikan ke supplier</option>
                        </select>
                        @error('tindakan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_inspeksi" class="form-label">Tanggal inspeksi *</label>
                        <input id="tanggal_inspeksi" name="tanggal_inspeksi" type="date" value="{{ $header['tanggal_inspeksi'] }}" required class="form-input">
                        @error('tanggal_inspeksi')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="catatan_inspeksi" class="form-label">Catatan inspeksi</label>
                        <textarea id="catatan_inspeksi" name="catatan_inspeksi" rows="3" class="form-textarea">{{ $header['catatan_inspeksi'] }}</textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <a href="{{ $isEdit ? route('inspeksi-retur.show', $inspeksi) : route('inspeksi-retur.index') }}" class="btn btn-ghost">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan hasil</button>
                </div>
            </section>
        </form>
    </div>
@endsection
