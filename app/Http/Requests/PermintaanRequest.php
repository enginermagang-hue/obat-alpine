<?php

namespace App\Http\Requests;

use App\Models\PermintaanObat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermintaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', PermintaanObat::class);
        }

        return $this->user()->can('update', $this->route('permintaan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal_permintaan' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
            'surat_permintaan' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.jumlah_diminta' => ['required', 'integer', 'min:1'],
            'rows.*.catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_permintaan.required' => 'Tanggal permintaan wajib diisi.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.jumlah_diminta.required' => 'Jumlah diminta wajib diisi.',
            'rows.*.jumlah_diminta.min' => 'Jumlah diminta minimal 1.',
        ];
    }
}
