<?php

namespace App\Observers;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en la bitacora de auditoria cada creacion y actualizacion observada.
 */
class AuditoriaObserver
{
    private const CAMPOS_EXCLUIDOS = [
        'password',
        'created_at',
        'updated_at',
    ];

    public function created(Model $modelo): void
    {
        Auditoria::create([
            'usuario_id' => $this->obtenerUsuarioId(),
            'accion' => 'crear',
            'tabla_afectada' => $modelo->getTable(),
            'registro_id' => $modelo->getKey(),
            'detalle' => [
                'antes' => null,
                'despues' => $this->filtrarCampos($modelo->getAttributes()),
            ],
            'fecha' => now(),
        ]);
    }

    public function updated(Model $modelo): void
    {
        $cambios = $this->filtrarCampos($modelo->getChanges());

        if ($cambios === []) {
            return;
        }

        $antes = [];
        foreach (array_keys($cambios) as $campo) {
            $antes[$campo] = $modelo->getOriginal($campo);
        }

        Auditoria::create([
            'usuario_id' => $this->obtenerUsuarioId(),
            'accion' => $this->determinarAccion($modelo, $cambios),
            'tabla_afectada' => $modelo->getTable(),
            'registro_id' => $modelo->getKey(),
            'detalle' => [
                'antes' => $antes,
                'despues' => $cambios,
            ],
            'fecha' => now(),
        ]);
    }

    private function obtenerUsuarioId(): ?int
    {
        $id = auth('api')->id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param array<string, mixed> $atributos
     * @return array<string, mixed>
     */
    private function filtrarCampos(array $atributos): array
    {
        return array_diff_key($atributos, array_flip(self::CAMPOS_EXCLUIDOS));
    }

    /**
     * @param array<string, mixed> $cambios
     */
    private function determinarAccion(Model $modelo, array $cambios): string
    {
        if (
            in_array($modelo->getTable(), ['ventas', 'pagos'], true)
            && isset($cambios['estado'])
            && in_array($cambios['estado'], ['anulada', 'anulado'], true)
        ) {
            return 'anular';
        }

        return 'actualizar';
    }
}

