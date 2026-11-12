<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
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
            'nombres' => ['required', 'string', 'max:150'],
            'dni' => ['required', 'string', 'regex:/^\d{8}$/', 'unique:clientes,dni'],
            'direccion' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_registro' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'El nombre completo del cliente es obligatorio.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.regex' => 'El DNI debe tener exactamente 8 dígitos numéricos.',
            'dni.unique' => 'Ya existe un cliente registrado con este DNI.',
            'direccion.required' => 'La dirección es obligatoria.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dni')) {
            $this->merge(['dni' => trim((string) $this->input('dni'))]);
        }
    }
}

