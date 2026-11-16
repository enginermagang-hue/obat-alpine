<?php

namespace App\Http\Requests;

use App\Models\LaporanRko;
use Illuminate\Foundation\Http\FormRequest;

class RkoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', LaporanRko::class);
        }

        return $this->user()->can('update', $this->route('rko'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal_pembuatan' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
            'rows' => ['nullable', 'array'],
            'rows.*.obat_id' => ['required_with:rows', 'integer', 'exists:obat,id'],
            'rows.*.pemakaian_tahun_sebelumnya' => ['nullable', 'integer', 'min:0'],
            'rows.*.rata_rata_pemakaian_bulanan' => ['nullable', 'integer', 'min:0'],
            'rows.*.stok_akhir' => ['nullable', 'integer', 'min:0'],
            'rows.*.kebutuhan_tahunan' => ['nullable', 'integer', 'min:0'],
            'rows.*.usulan' => ['nullable', 'integer', 'min:0'],
            'rows.*.harga_perkiraan' => ['nullable', 'numeric', 'min:0'],
            'rows.*.buffer_stock_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_pembuatan.required' => 'Tanggal pembuatan wajib diisi.',
        ];
    }
}
