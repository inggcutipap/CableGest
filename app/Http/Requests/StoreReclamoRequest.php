<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReclamoRequest extends FormRequest
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
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'contrato_id' => ['nullable', 'integer', 'exists:contratos,id'],
            'motivo' => ['required', 'string', 'max:255'],
            'fecha_registro' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Debes seleccionar un cliente.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'contrato_id.exists' => 'El contrato seleccionado no existe.',
            'motivo.required' => 'Debes describir el motivo del reclamo.',
        ];
    }
}
