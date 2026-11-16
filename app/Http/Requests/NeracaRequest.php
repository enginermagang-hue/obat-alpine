<?php

namespace App\Http\Requests;

use App\Models\NeracaTahunan;
use Illuminate\Foundation\Http\FormRequest;

class NeracaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', NeracaTahunan::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tahun.required' => 'Tahun wajib diisi.',
        ];
    }
}
