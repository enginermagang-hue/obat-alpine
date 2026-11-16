<?php

namespace App\Http\Requests;

use App\Models\FasilitasKesehatan;
use App\Models\OpnameStok;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', OpnameStok::class);
        }

        return $this->user()->can('update', $this->route('opname'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::in(['penyesuaian', 'stok_awal', 'stok_baru'])],
            'tanggal_opname' => ['required', 'date'],
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'status' => ['required', Rule::in(['draft', 'proses', 'selesai'])],
            'catatan' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.batch_id' => ['nullable', 'integer', 'exists:batch_stok,id'],
            'rows.*.stok_fisik' => ['required', 'integer', 'min:0'],
            'rows.*.batch_number' => ['nullable', 'string', 'max:100'],
            'rows.*.tanggal_expired' => ['nullable', 'date'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe.required' => 'Tipe opname wajib dipilih.',
            'tanggal_opname.required' => 'Tanggal opname wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.stok_fisik.required' => 'Stok fisik wajib diisi.',
            'rows.*.stok_fisik.min' => 'Stok fisik tidak boleh negatif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->user()->fasilitas_kesehatan_id)) {
            $this->merge(['fasilitas_id' => $this->user()->fasilitas_kesehatan_id]);
        }
    }
}
