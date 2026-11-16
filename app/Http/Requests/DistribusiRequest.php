<?php

namespace App\Http\Requests;

use App\Models\DistribusiObat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistribusiRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', DistribusiObat::class);
        }

        return $this->user()->can('update', $this->route('distribusi'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipe_distribusi' => ['required', Rule::in(['dinas_ke_puskesmas', 'puskesmas_ke_pustu'])],
            'tanggal_kirim' => ['nullable', 'date'],
            'fasilitas_pengirim_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'fasilitas_penerima_id' => ['required', 'integer', 'exists:fasilitas_kesehatan,id', 'different:fasilitas_pengirim_id'],
            'permintaan_id' => ['nullable', 'integer', 'exists:permintaan_obat,id'],
            'catatan' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.jumlah' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe_distribusi.required' => 'Tipe distribusi wajib dipilih.',
            'fasilitas_penerima_id.required' => 'Faskes penerima wajib dipilih.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.jumlah.required' => 'Jumlah wajib diisi.',
            'rows.*.jumlah.min' => 'Jumlah minimal 1.',
        ];
    }
}
