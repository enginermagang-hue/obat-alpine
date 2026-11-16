<?php

namespace App\Http\Requests;

use App\Models\PemakaianObat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PemakaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', PemakaianObat::class);
        }

        return $this->user()->can('update', $this->route('pemakaian'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal_pemakaian' => ['required', 'date'],
            'jenis_pelayanan' => ['required', Rule::in(['rawat_jalan', 'rawat_inap', 'uks', 'posyandu', 'pusling', 'gigi', 'laboratorium', 'apotek', 'lainnya'])],
            'nama_pasien' => ['nullable', 'string', 'max:255'],
            'no_rekam_medis' => ['nullable', 'string', 'max:50'],
            'diagnosa_kode' => ['nullable', 'string', 'max:50'],
            'catatan' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.jumlah' => ['required', 'integer', 'min:1'],
            'rows.*.dosis' => ['nullable', 'string', 'max:100'],
            'rows.*.satuan_dosis' => ['nullable', 'string', 'max:50'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_pemakaian.required' => 'Tanggal pemakaian wajib diisi.',
            'jenis_pelayanan.required' => 'Jenis pelayanan wajib dipilih.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.jumlah.required' => 'Jumlah wajib diisi.',
            'rows.*.jumlah.min' => 'Jumlah minimal 1.',
        ];
    }
}
