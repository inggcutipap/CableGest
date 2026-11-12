<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('serie') && is_string($this->serie)) {
            $this->merge([
                'serie' => mb_strtoupper(trim($this->serie)),
            ]);
        }

        if ($this->has('marca') && is_string($this->marca)) {
            $this->merge([
                'marca' => trim($this->marca),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'catalogo_id' => ['required', 'integer', 'exists:catalogo_servicios,id'],
            'marca' => ['required', 'string', 'max:50'],
            'serie' => ['required', 'string', 'max:80', 'unique:equipos,serie'],
            'motivo' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catalogo_id.required' => 'Debes seleccionar un tipo de equipo del catalogo.',
            'catalogo_id.exists' => 'El item de catalogo seleccionado no existe.',
            'marca.required' => 'La marca del equipo es obligatoria.',
            'serie.required' => 'El numero de serie es obligatorio.',
            'serie.unique' => 'Ya existe un equipo registrado con este numero de serie.',
        ];
    }
}
