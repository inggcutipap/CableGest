<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Reclamo;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta el ciclo de vida de reclamos: registro, asignacion de tecnico y diagnostico.
 */
class ReclamoService
{
    /**
     * @param array<string, mixed> $datos
     */
    public function registrar(array $datos, int $usuarioId): Reclamo
    {
        if (! empty($datos['contrato_id'])) {
            /** @var Contrato $contrato */
            $contrato = Contrato::query()->findOrFail((int) $datos['contrato_id']);
            if ($contrato->cliente_id !== (int) $datos['cliente_id']) {
                throw ValidationException::withMessages([
                    'contrato_id' => ['El contrato seleccionado no pertenece al cliente indicado.'],
                ]);
            }
        }

        $reclamo = Reclamo::create([
            'cliente_id' => (int) $datos['cliente_id'],
            'contrato_id' => isset($datos['contrato_id']) && $datos['contrato_id'] !== '' ? (int) $datos['contrato_id'] : null,
            'usuario_registro_id' => $usuarioId,
            'tecnico_id' => null,
            'motivo' => trim((string) $datos['motivo']),
            'diagnostico' => 'pendiente',
            'estado' => 'abierto',
            'fecha_registro' => (string) ($datos['fecha_registro'] ?? now()->toDateString()),
            'observaciones' => isset($datos['observaciones']) && $datos['observaciones'] !== null
                ? trim((string) $datos['observaciones'])
                : null,
        ]);

        return $reclamo->load([
            'cliente:id,nombres,dni,telefono,direccion',
            'contrato:id,codigo_contrato,estado',
            'usuarioRegistro:id,nombre',
            'tecnico:id,nombre,rol',
        ]);
    }

    public function asignarTecnico(Reclamo $reclamo, int $tecnicoId): Reclamo
    {
        return DB::transaction(function () use ($reclamo, $tecnicoId): Reclamo {
            /** @var Reclamo $reclamoBloqueado */
            $reclamoBloqueado = Reclamo::query()
                ->whereKey($reclamo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reclamoBloqueado->estado === 'resuelto') {
                throw ValidationException::withMessages([
                    'estado' => ['No se puede asignar un tecnico a un reclamo ya resuelto.'],
                ]);
            }

            /** @var Usuario $tecnico */
            $tecnico = Usuario::query()->findOrFail($tecnicoId);
            if (! $tecnico->activo) {
                throw ValidationException::withMessages([
                    'tecnico_id' => ['El usuario seleccionado se encuentra inactivo.'],
                ]);
            }

            $reclamoBloqueado->update([
                'tecnico_id' => $tecnico->id,
                'estado' => 'en_proceso',
            ]);

            return $reclamoBloqueado->fresh([
                'cliente:id,nombres,dni,telefono,direccion',
                'contrato:id,codigo_contrato,estado',
                'usuarioRegistro:id,nombre',
                'tecnico:id,nombre,rol',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function registrarDiagnostico(Reclamo $reclamo, array $datos): Reclamo
    {
        return DB::transaction(function () use ($reclamo, $datos): Reclamo {
            /** @var Reclamo $reclamoBloqueado */
            $reclamoBloqueado = Reclamo::query()
                ->whereKey($reclamo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reclamoBloqueado->estado === 'resuelto') {
                throw ValidationException::withMessages([
                    'estado' => ['El reclamo ya cuenta con diagnostico y cierre registrado.'],
                ]);
            }

            $reclamoBloqueado->update([
                'diagnostico' => (string) $datos['diagnostico'],
                'observaciones' => trim((string) $datos['observaciones']),
                'fecha_atencion' => (string) ($datos['fecha_atencion'] ?? now()->toDateString()),
                'estado' => 'resuelto',
            ]);

            return $reclamoBloqueado->fresh([
                'cliente:id,nombres,dni,telefono,direccion',
                'contrato:id,codigo_contrato,estado',
                'usuarioRegistro:id,nombre',
                'tecnico:id,nombre,rol',
            ]);
        });
    }
}
