<?php

namespace App\Http\Requests;

use App\Models\SumberDana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SumberDanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', SumberDana::class);
        }

        return $this->user()->can('update', $this->route('sumber_dana'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dana = $this->route('sumber_dana');

        return [
            'kode' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:255'],
            'tahun' => [
                'required', 'integer', 'min:2000', 'max:2100',
                $dana
                    ? Rule::unique('sumber_dana')->where('kode', $this->input('kode'))->ignore($dana->id)
                    : Rule::unique('sumber_dana')->where('kode', $this->input('kode')),
            ],
            'total_anggaran' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode sumber dana wajib diisi.',
            'nama.required' => 'Nama sumber dana wajib diisi.',
            'tahun.required' => 'Tahun anggaran wajib diisi.',
            'tahun.unique' => 'Kombinasi kode dan tahun sudah dipakai.',
            'total_anggaran.required' => 'Total anggaran wajib diisi.',
            'total_anggaran.numeric' => 'Total anggaran harus berupa angka.',
            'total_anggaran.min' => 'Total anggaran tidak boleh negatif.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }
}
