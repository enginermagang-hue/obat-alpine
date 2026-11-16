<?php

namespace App\Http\Requests;

use App\Models\LaporanLplpo;
use Illuminate\Foundation\Http\FormRequest;

class LplpoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', LaporanLplpo::class);
        }

        return $this->user()->can('update', $this->route('lplpo'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'periode_bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'periode_tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_pembuatan' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
            'rows' => ['nullable', 'array'],
            'rows.*.obat_id' => ['required_with:rows', 'integer', 'exists:obat,id'],
            'rows.*.stok_awal' => ['nullable', 'integer', 'min:0'],
            'rows.*.jumlah_masuk' => ['nullable', 'integer', 'min:0'],
            'rows.*.jumlah_keluar' => ['nullable', 'integer', 'min:0'],
            'rows.*.stok_optimum' => ['nullable', 'integer', 'min:0'],
            'rows.*.permintaan_selanjutnya' => ['nullable', 'integer', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periode_bulan.required' => 'Periode bulan wajib dipilih.',
            'periode_tahun.required' => 'Periode tahun wajib diisi.',
            'tanggal_pembuatan.required' => 'Tanggal pembuatan wajib diisi.',
        ];
    }
}
