<?php

namespace App\Http\Requests;

use App\Models\UserPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar_type' => ['required', Rule::in(['initials', 'dicebear', 'preset'])],
            'avatar_dicebear_style' => ['nullable', 'string', Rule::in(array_keys(UserPreference::dicebearStyles()))],
            'avatar_path' => ['nullable', 'string', 'max:255'],
            'posisi_navbar' => ['required', Rule::in(['sidebar', 'topbar'])],
            'sidebar_collapsed' => ['sometimes', 'boolean'],
            'bahasa' => ['required', Rule::in(['id', 'en'])],
            'items_per_halaman' => ['required', 'integer', 'in:10,25,50,100'],
            'notifikasi_email' => ['sometimes', 'boolean'],
            'notifikasi_browser' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'avatar_type.required' => 'Tipe avatar wajib dipilih.',
            'bahasa.required' => 'Bahasa wajib dipilih.',
            'items_per_halaman.in' => 'Jumlah item per halaman harus 10, 25, 50, atau 100.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sidebar_collapsed' => $this->boolean('sidebar_collapsed'),
            'notifikasi_email' => $this->boolean('notifikasi_email'),
            'notifikasi_browser' => $this->boolean('notifikasi_browser'),
        ]);
    }
}
