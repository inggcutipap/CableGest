<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiagnosticoReclamoRequest extends FormRequest
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
            'diagnostico' => ['required', Rule::in(['falla_confirmada', 'sin_falla'])],
            'observaciones' => ['required', 'string', 'max:500'],
            'fecha_atencion' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'diagnostico.required' => 'Debes indicar el diagnostico del reclamo.',
            'diagnostico.in' => 'El diagnostico debe ser falla_confirmada o sin_falla.',
            'observaciones.required' => 'Debes ingresar las observaciones tecnicas de la atencion.',
        ];
    }
}
