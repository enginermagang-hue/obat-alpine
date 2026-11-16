<?php

namespace App\Http\Requests;

use App\Models\FasilitasKesehatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FasilitasKesehatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', FasilitasKesehatan::class);
        }

        return $this->user()->can('update', $this->route('fasilitas'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $faskes = $this->route('fasilitas');

        return [
            'kode_faskes' => [
                'required', 'string', 'max:50',
                $faskes ? Rule::unique('fasilitas_kesehatan', 'kode_faskes')->ignore($faskes->id) : 'unique:fasilitas_kesehatan,kode_faskes',
            ],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', Rule::in(['puskesmas', 'pustu'])],
            'puskesmas_induk_id' => [
                'nullable', 'integer', 'exists:fasilitas_kesehatan,id',
                Rule::requiredIf(fn () => $this->input('tipe') === 'pustu'),
                function (string $attribute, mixed $value, \Closure $fail) use ($faskes): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($faskes && (int) $value === (int) $faskes->id) {
                        $fail('Puskesmas induk tidak boleh faskes itu sendiri.');

                        return;
                    }

                    $induk = FasilitasKesehatan::find($value);

                    if ($induk && $induk->tipe !== 'puskesmas') {
                        $fail('Puskesmas induk harus bertipe puskesmas.');
                    }
                },
            ],
            'alamat' => ['required', 'string'],
            'pic' => ['nullable', 'string', 'max:255'],
            'kontak_pic' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'kepala_faskes' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_faskes.required' => 'Kode faskes wajib diisi.',
            'kode_faskes.unique' => 'Kode faskes sudah dipakai.',
            'nama.required' => 'Nama fasilitas wajib diisi.',
            'tipe.required' => 'Tipe fasilitas wajib dipilih.',
            'puskesmas_induk_id.required' => 'Pustu wajib memilih puskesmas induk.',
            'puskesmas_induk_id.exists' => 'Puskesmas induk tidak ditemukan.',
            'alamat.required' => 'Alamat wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('tipe') === 'puskesmas') {
            $this->merge(['puskesmas_induk_id' => null]);
        }
    }
}
