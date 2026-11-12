<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
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
            'fecha_venta' => ['nullable', 'date'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.catalogo_id' => ['required', 'integer', 'exists:catalogo_servicios,id'],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
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
            'fecha_venta.date' => 'La fecha de venta no tiene un formato válido.',
            'lineas.required' => 'Debes agregar al menos una línea a la venta.',
            'lineas.min' => 'Debes agregar al menos una línea a la venta.',
            'lineas.*.catalogo_id.required' => 'Cada línea debe especificar un servicio o producto del catálogo.',
            'lineas.*.catalogo_id.exists' => 'Uno de los ítems del catálogo seleccionados no existe.',
            'lineas.*.cantidad.required' => 'Cada línea debe especificar una cantidad.',
            'lineas.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'lineas.*.cantidad.min' => 'La cantidad mínima por línea es 1.',
        ];
    }
}
