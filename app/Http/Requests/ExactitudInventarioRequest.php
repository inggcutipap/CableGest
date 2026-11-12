<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExactitudInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'series' => ['required', 'array'],
            'series.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'series.required' => 'Debes enviar la lista de series contadas fisicamente.',
            'series.array' => 'Las series deben enviarse como un listado.',
        ];
    }
}
