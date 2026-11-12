<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstalarEquipoRequest extends FormRequest
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
            'contrato_id' => ['required', 'integer', 'exists:contratos,id'],
            'fecha_instalacion' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrato_id.required' => 'Debes seleccionar el contrato donde se instala el equipo.',
            'contrato_id.exists' => 'El contrato seleccionado no existe.',
            'fecha_instalacion.date' => 'La fecha de instalacion no tiene un formato valido.',
        ];
    }
}
