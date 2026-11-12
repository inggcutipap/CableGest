<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignarReclamoRequest extends FormRequest
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
            'tecnico_id' => ['required', 'integer', 'exists:usuarios,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tecnico_id.required' => 'Debes seleccionar un tecnico para asignar el reclamo.',
            'tecnico_id.exists' => 'El tecnico seleccionado no existe.',
        ];
    }
}
