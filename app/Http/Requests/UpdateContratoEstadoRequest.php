<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContratoEstadoRequest extends FormRequest
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
            'estado' => ['required', Rule::in(['activo', 'suspendido', 'baja'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado del contrato es obligatorio.',
            'estado.in' => 'El estado debe ser activo, suspendido o baja.',
        ];
    }
}
