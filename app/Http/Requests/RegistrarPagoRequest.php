<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarPagoRequest extends FormRequest
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
            'fecha_pago' => ['nullable', 'date'],
            'metodo_pago' => ['nullable', 'string', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'tarjeta'])],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_pago.date' => 'La fecha de pago no tiene un formato válido.',
            'metodo_pago.in' => 'El método de pago debe ser efectivo, yape, plin, transferencia o tarjeta.',
        ];
    }
}
