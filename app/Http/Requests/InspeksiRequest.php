<?php

namespace App\Http\Requests;

use App\Models\InspeksiRetur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspeksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', InspeksiRetur::class);
        }

        return $this->user()->can('update', $this->route('inspeksi_retur'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'retur_id' => ['required', 'integer', 'exists:retur_obat,id'],
            'detail_retur_id' => ['required', 'integer', 'exists:detail_retur_obat,id'],
            'batch_id' => ['required', 'integer', 'exists:batch_stok,id'],
            'hasil_inspeksi' => ['required', Rule::in(['layak', 'tidak_layak', 'perlu_tindakan_lanjut'])],
            'tindakan' => ['required', Rule::in(['didistribusi_kembali', 'dimusnahkan', 'dikembalikan_ke_supplier'])],
            'catatan_inspeksi' => ['nullable', 'string'],
            'tanggal_inspeksi' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'retur_id.required' => 'Retur wajib dipilih.',
            'detail_retur_id.required' => 'Item retur wajib dipilih.',
            'batch_id.required' => 'Batch wajib dipilih.',
            'hasil_inspeksi.required' => 'Hasil inspeksi wajib dipilih.',
            'tindakan.required' => 'Tindakan wajib dipilih.',
            'tanggal_inspeksi.required' => 'Tanggal inspeksi wajib diisi.',
        ];
    }
}
