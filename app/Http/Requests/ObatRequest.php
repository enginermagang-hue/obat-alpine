<?php

namespace App\Http\Requests;

use App\Enums\MetodeStok;
use App\Models\Obat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', Obat::class);
        }

        return $this->user()->can('update', $this->route('obat'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $obat = $this->route('obat');

        return [
            'kode_obat' => [
                'required', 'string', 'max:50',
                $obat ? Rule::unique('obat', 'kode_obat')->ignore($obat->id) : 'unique:obat,kode_obat',
            ],
            'nama_obat' => ['required', 'string', 'max:255'],
            'nama_generik' => ['nullable', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:100'],
            'satuan' => ['required', 'string', 'max:50'],
            'kekuatan' => ['nullable', 'string', 'max:100'],
            'bentuk_sediaan' => ['required', Rule::in(['tablet', 'kapsul', 'sirup', 'salep', 'injeksi', 'drop', 'inhaler', 'suppositoria'])],
            'produsen' => ['nullable', 'string', 'max:255'],
            'kemasan' => ['nullable', 'string', 'max:255'],
            'harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'ven_kategori' => ['nullable', Rule::in(['V', 'E', 'N'])],
            'metode_stok' => ['required', Rule::enum(MetodeStok::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_obat.required' => 'Kode obat wajib diisi.',
            'kode_obat.unique' => 'Kode obat sudah dipakai.',
            'nama_obat.required' => 'Nama obat wajib diisi.',
            'kategori.required' => 'Kategori wajib diisi.',
            'satuan.required' => 'Satuan wajib diisi.',
            'bentuk_sediaan.required' => 'Bentuk sediaan wajib dipilih.',
            'bentuk_sediaan.in' => 'Bentuk sediaan tidak valid.',
            'status.required' => 'Status wajib dipilih.',
            'metode_stok.required' => 'Metode stok wajib dipilih.',
            'harga_satuan.numeric' => 'Harga satuan harus berupa angka.',
            'harga_satuan.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
