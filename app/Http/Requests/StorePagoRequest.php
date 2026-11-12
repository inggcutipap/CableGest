<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
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
            'periodo' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_vencimiento' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrato_id.required' => 'Debes seleccionar un contrato.',
            'contrato_id.exists' => 'El contrato seleccionado no existe.',
            'periodo.regex' => 'El periodo debe tener el formato AAAA-MM (ejemplo: 2026-11).',
            'monto.required' => 'El monto es obligatorio.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
        ];
    }
}
