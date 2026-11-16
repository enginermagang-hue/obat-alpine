<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('post')) {
            return $this->user()->can('create', User::class);
        }

        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                $target ? Rule::unique('users', 'email')->ignore($target->id) : 'unique:users,email',
            ],
            'password' => $this->isMethod('post')
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable', 'string', 'min:8', 'confirmed'],
            'fasilitas_kesehatan_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'roles' => [
                'required', 'array', 'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $target = $this->route('user');

                    if ($target && $this->user()->is($target)) {
                        $current = $target->getRoleNames()->sort()->values()->toArray();
                        $incoming = collect((array) $value)->sort()->values()->toArray();

                        if ($current !== $incoming) {
                            $fail('Tidak dapat mengubah role akun sendiri.');
                        }
                    }
                },
            ],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email sudah dipakai.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'fasilitas_kesehatan_id.exists' => 'Faskes tidak ditemukan.',
            'roles.required' => 'Pilih minimal satu role.',
            'roles.min' => 'Pilih minimal satu role.',
        ];
    }
}
