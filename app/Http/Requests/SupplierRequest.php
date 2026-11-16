<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', Supplier::class);
        }

        return $this->user()->can('update', $this->route('supplier'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'nama' => [
                'required', 'string', 'max:255',
                $supplier ? Rule::unique('suppliers', 'nama')->ignore($supplier->id) : 'unique:suppliers,nama',
            ],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama supplier wajib diisi.',
            'nama.unique' => 'Nama supplier sudah dipakai.',
            'email.email' => 'Format email tidak valid.',
            'status.required' => 'Status wajib dipilih.',
        ];
    }
}
