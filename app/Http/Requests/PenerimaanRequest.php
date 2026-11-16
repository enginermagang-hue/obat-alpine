<?php

namespace App\Http\Requests;

use App\Models\PenerimaanStok;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenerimaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', PenerimaanStok::class);
        }

        return $this->user()->can('update', $this->route('penerimaan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::in(['pembelian', 'hibah'])],
            'tanggal_penerimaan' => ['required', 'date'],
            'fasilitas_id' => [
                'nullable', 'integer', 'exists:fasilitas_kesehatan,id',
            ],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'sumber_dana_id' => ['nullable', 'integer', 'exists:sumber_dana,id'],
            'nomor_po' => ['nullable', 'string', 'max:100'],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.batch_number' => ['required', 'string', 'max:100'],
            'rows.*.tanggal_expired' => ['required', 'date', 'after:today'],
            'rows.*.jumlah' => ['required', 'integer', 'min:1'],
            'rows.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe.required' => 'Tipe penerimaan wajib dipilih.',
            'tanggal_penerimaan.required' => 'Tanggal penerimaan wajib diisi.',
            'fasilitas_id.prohibited' => 'Penerimaan Anda otomatis tercatat di faskes sendiri.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.batch_number.required' => 'Nomor batch wajib diisi.',
            'rows.*.tanggal_expired.required' => 'Tanggal kedaluwarsa wajib diisi.',
            'rows.*.tanggal_expired.after' => 'Tanggal kedaluwarsa harus di masa depan.',
            'rows.*.jumlah.required' => 'Jumlah wajib diisi.',
            'rows.*.jumlah.min' => 'Jumlah minimal 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->user()->fasilitas_kesehatan_id)) {
            $this->merge(['fasilitas_id' => $this->user()->fasilitas_kesehatan_id]);
        }
    }
}
