<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalidaEquipoRequest extends FormRequest
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
            'estado' => ['nullable', Rule::in(['en_reparacion', 'baja'])],
            'motivo' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.in' => 'El estado de salida debe ser en_reparacion o baja.',
            'motivo.required' => 'Debes indicar el motivo de la salida del equipo.',
        ];
    }
}
