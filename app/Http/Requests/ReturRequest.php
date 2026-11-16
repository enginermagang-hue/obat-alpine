<?php

namespace App\Http\Requests;

use App\Models\ReturObat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', ReturObat::class);
        }

        return $this->user()->can('update', $this->route('retur'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipe_retur' => ['required', Rule::in(['puskesmas_ke_gudang', 'pustu_ke_puskesmas', 'gudang_ke_supplier'])],
            'tanggal_retur' => ['required', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'fasilitas_penerima_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'alasan' => ['required', Rule::in(['expired', 'rusak', 'kelebihan_stok', 'salah_kirim', 'recall', 'near_expiry', 'lainnya'])],
            'alasan_lainnya' => ['nullable', 'string', Rule::requiredIf(fn () => $this->input('alasan') === 'lainnya')],
            'catatan' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.obat_id' => ['required', 'integer', 'exists:obat,id'],
            'rows.*.batch_id' => ['required', 'integer', 'exists:batch_stok,id'],
            'rows.*.jumlah_retur' => ['required', 'integer', 'min:1'],
            'rows.*.bukti' => ['nullable', 'file', 'image', 'max:2048'],
            'rows.*.keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe_retur.required' => 'Tipe retur wajib dipilih.',
            'tanggal_retur.required' => 'Tanggal retur wajib diisi.',
            'alasan.required' => 'Alasan retur wajib dipilih.',
            'alasan_lainnya.required' => 'Jelaskan alasan lainnya.',
            'rows.required' => 'Tambahkan minimal satu baris item.',
            'rows.min' => 'Tambahkan minimal satu baris item.',
            'rows.*.obat_id.required' => 'Setiap baris wajib memilih obat.',
            'rows.*.batch_id.required' => 'Setiap baris wajib memilih batch.',
            'rows.*.jumlah_retur.required' => 'Jumlah retur wajib diisi.',
            'rows.*.jumlah_retur.min' => 'Jumlah retur minimal 1.',
            'rows.*.bukti.image' => 'Bukti harus berupa gambar.',
            'rows.*.bukti.max' => 'Ukuran bukti maksimal 2 MB.',
        ];
    }
}
