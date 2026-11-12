<?php

namespace App\Http\Requests;

use App\Models\CatalogoServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogoRequest extends FormRequest
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
        /** @var CatalogoServicio $catalogo */
        $catalogo = $this->route('catalogo');

        return [
            'codigo_interno' => [
                'required',
                'string',
                'max:10',
                Rule::unique('catalogo_servicios', 'codigo_interno')->ignore($catalogo->id),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => ['required', Rule::in(CatalogoServicio::TIPOS)],
            'segmento' => ['nullable', Rule::in(CatalogoServicio::SEGMENTOS)],
            'velocidad_mbps' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_interno.required' => 'El código interno es obligatorio.',
            'codigo_interno.unique' => 'Ya existe otro ítem con ese código interno.',
            'nombre.required' => 'La descripción del ítem es obligatoria.',
            'tipo.required' => 'El tipo de ítem es obligatorio.',
            'tipo.in' => 'El tipo seleccionado no es válido.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo_interno')) {
            $this->merge(['codigo_interno' => trim((string) $this->input('codigo_interno'))]);
        }
    }
}

