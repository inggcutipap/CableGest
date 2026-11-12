<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Venta;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Sprint2Test extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConToken(): array
    {
        $faker = FakerFactory::create('es_PE');

        $usuario = Usuario::create([
            'nombre' => $faker->name(),
            'correo' => $faker->unique()->safeEmail(),
            'password' => 'ClavePrueba123!',
            'rol' => 'operador',
            'activo' => true,
        ]);

        return [$usuario, (string) auth('api')->login($usuario)];
    }

    private function crearClienteFicticio(?string $dni = null): Cliente
    {
        $faker = FakerFactory::create('es_PE');

        return Cliente::create([
            'nombres' => $faker->name(),
            'dni' => $dni ?? $faker->unique()->numerify('########'),
            'direccion' => $faker->streetAddress(),
            'telefono' => $faker->numerify('9########'),
            'fecha_registro' => '2026-11-12',
        ]);
    }

    public function test_cp07_venta_con_plan_instalacion_y_equipo_calcula_tres_lineas_y_total(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S0000029',
            'nombre' => 'SOLO INTERNET 40 MG',
            'tipo' => 'plan_internet',
            'segmento' => 'residencial',
            'velocidad_mbps' => 40,
            'precio' => '40.00',
            'activo' => true,
        ]);

        $instalacion = CatalogoServicio::create([
            'codigo_interno' => 'S0000023',
            'nombre' => 'INSTALACION DE INTERNET',
            'tipo' => 'cargo_unico',
            'precio' => '100.00',
            'activo' => true,
        ]);

        $equipo = CatalogoServicio::create([
            'codigo_interno' => 'A0000008',
            'nombre' => 'TP link (caja x unidad)',
            'tipo' => 'equipo',
            'precio' => '80.00',
            'activo' => true,
        ]);

        $respuesta = $this->withToken($token)->postJson('/api/ventas', [
            'cliente_id' => $cliente->id,
            'fecha_venta' => '2026-11-12',
            'lineas' => [
                ['catalogo_id' => $plan->id, 'cantidad' => 1],
                ['catalogo_id' => $instalacion->id, 'cantidad' => 1],
                ['catalogo_id' => $equipo->id, 'cantidad' => 2],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.monto_total', '300.00')
            ->assertJsonPath('data.estado', 'registrada')
            ->assertJsonPath('data.usuario_id', $usuario->id);

        $this->assertCount(3, $respuesta->json('data.detalles'));
        $this->assertDatabaseHas('ventas', [
            'cliente_id' => $cliente->id,
            'monto_total' => '300.00',
            'estado' => 'registrada',
        ]);
    }

    public function test_cp08_venta_de_un_plan_genera_contrato_asociado(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S0000024',
            'nombre' => 'DUO TV CABLE INTERNET',
            'tipo' => 'plan_combo',
            'segmento' => 'residencial',
            'precio' => '100.00',
            'activo' => true,
        ]);

        $cargo = CatalogoServicio::create([
            'codigo_interno' => 'S0000016',
            'nombre' => 'SRV INSTALACION ADICIONAL',
            'tipo' => 'cargo_unico',
            'precio' => '35.00',
            'activo' => true,
        ]);

        $respuesta = $this->withToken($token)->postJson('/api/ventas', [
            'cliente_id' => $cliente->id,
            'fecha_venta' => '2026-11-12',
            'lineas' => [
                ['catalogo_id' => $plan->id, 'cantidad' => 1],
                ['catalogo_id' => $cargo->id, 'cantidad' => 1],
            ],
        ]);

        $respuesta->assertCreated();

        $this->assertDatabaseCount('contratos', 1);
        $this->assertDatabaseHas('contratos', [
            'cliente_id' => $cliente->id,
            'catalogo_id' => $plan->id,
            'fecha_inicio' => '2026-11-12',
            'estado' => 'activo',
        ]);
    }

    public function test_no_permite_vender_item_inactivo_del_catalogo(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $planInactivo = CatalogoServicio::create([
            'codigo_interno' => 'S0000010',
            'nombre' => 'PLAN DESACTIVADO',
            'tipo' => 'plan_tv',
            'segmento' => 'residencial',
            'precio' => '30.00',
            'activo' => false,
        ]);

        $this->withToken($token)
            ->postJson('/api/ventas', [
                'cliente_id' => $cliente->id,
                'lineas' => [
                    ['catalogo_id' => $planInactivo->id, 'cantidad' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lineas.0.catalogo_id']);

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_cp20_crear_y_anular_venta_registra_auditoria_y_da_de_baja_al_contrato(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S0000001',
            'nombre' => 'TELEVISION POR CABLE',
            'tipo' => 'plan_tv',
            'segmento' => 'residencial',
            'precio' => '36.00',
            'activo' => true,
        ]);

        $creacion = $this->withToken($token)->postJson('/api/ventas', [
            'cliente_id' => $cliente->id,
            'fecha_venta' => '2026-11-12',
            'lineas' => [['catalogo_id' => $plan->id, 'cantidad' => 1]],
        ]);

        $creacion->assertCreated();
        $ventaId = (int) $creacion->json('data.id');
        $contratoId = (int) $creacion->json('data.detalles.0.contrato.id');

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'crear',
            'tabla_afectada' => 'ventas',
            'registro_id' => $ventaId,
        ]);
        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'crear',
            'tabla_afectada' => 'contratos',
            'registro_id' => $contratoId,
        ]);

        $this->withToken($token)
            ->patchJson("/api/ventas/{$ventaId}/anular", [
                'motivo_anulacion' => 'Cliente desistio antes de la instalacion',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulada');

        $this->assertDatabaseHas('contratos', [
            'id' => $contratoId,
            'estado' => 'baja',
        ]);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'anular',
            'tabla_afectada' => 'ventas',
            'registro_id' => $ventaId,
        ]);

        $registroContrato = Auditoria::query()
            ->where('tabla_afectada', 'contratos')
            ->where('registro_id', $contratoId)
            ->where('accion', 'actualizar')
            ->first();
        $this->assertNotNull($registroContrato);
        $this->assertSame('baja', $registroContrato->detalle['despues']['estado']);

        // No debe permitir reactivar un contrato cuya venta esta anulada.
        $this->withToken($token)
            ->patchJson("/api/contratos/{$contratoId}/estado", ['estado' => 'activo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_no_permite_anular_venta_si_contrato_tiene_pagos_registrados(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S0000002',
            'nombre' => 'TELEVISION X CABLE',
            'tipo' => 'plan_tv',
            'segmento' => 'residencial',
            'precio' => '40.00',
            'activo' => true,
        ]);

        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_venta' => '2026-11-12',
            'monto_total' => '40.00',
            'estado' => 'registrada',
        ]);

        $detalle = $venta->detalles()->create([
            'catalogo_id' => $plan->id,
            'cantidad' => 1,
            'precio_unitario' => '40.00',
            'subtotal' => '40.00',
        ]);

        $contrato = Contrato::create([
            'codigo_contrato' => 'CG-000099',
            'cliente_id' => $cliente->id,
            'catalogo_id' => $plan->id,
            'venta_detalle_id' => $detalle->id,
            'fecha_inicio' => '2026-11-12',
            'estado' => 'activo',
        ]);

        Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-11',
            'monto' => '40.00',
            'fecha_vencimiento' => '2026-11-30',
            'fecha_pago' => '2026-11-12',
            'estado' => 'pagado',
        ]);

        $this->withToken($token)
            ->patchJson("/api/ventas/{$venta->id}/anular")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['venta']);

        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'estado' => 'registrada',
        ]);
        $this->assertDatabaseHas('contratos', [
            'id' => $contrato->id,
            'estado' => 'activo',
        ]);
    }

    public function test_permite_consultar_y_cambiar_estado_de_contrato(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        $cliente = $this->crearClienteFicticio();

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S0000003',
            'nombre' => 'TV POR CABLE',
            'tipo' => 'plan_tv',
            'segmento' => 'residencial',
            'precio' => '50.00',
            'activo' => true,
        ]);

        $contrato = Contrato::create([
            'codigo_contrato' => 'Z01-000501',
            'cliente_id' => $cliente->id,
            'catalogo_id' => $plan->id,
            'venta_detalle_id' => null,
            'fecha_inicio' => '2026-01-15',
            'estado' => 'activo',
        ]);

        $this->withToken($token)
            ->getJson('/api/contratos?buscar=Z01-000501')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.codigo_contrato', 'Z01-000501');

        $this->withToken($token)
            ->patchJson("/api/contratos/{$contrato->id}/estado", ['estado' => 'suspendido'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'suspendido');
    }

    public function test_cp22_cargar_clientes_depurados_desde_excel_sin_duplicados(): void
    {
        $faker = FakerFactory::create('es_PE');

        CatalogoServicio::create([
            'codigo_interno' => 'S0000001',
            'nombre' => 'TELEVISION POR CABLE',
            'tipo' => 'plan_tv',
            'segmento' => 'residencial',
            'precio' => '36.00',
            'activo' => true,
        ]);

        $dniUno = $faker->unique()->numerify('########');
        $dniDos = '0' . $faker->unique()->numerify('#######');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['dni', 'nombres', 'direccion', 'telefono', 'codigo_plan', 'codigo_contrato', 'fecha_inicio', 'estado'],
        ]);

        $filas = [
            [$dniUno, $faker->name(), $faker->streetAddress(), $faker->numerify('9########'), 'S0000001', 'Z01-000001', '2026-03-10', 'ACTIVO'],
            [$dniDos, $faker->name(), $faker->streetAddress(), $faker->numerify('9########'), 'S0000001', 'Z01-000002', '2026-04-15', 'CORTADO'],
            [$dniUno, $faker->name(), $faker->streetAddress(), $faker->numerify('9########'), 'S0000001', 'Z01-000003', '2026-05-01', 'ACTIVO'], // DNI duplicado en archivo
            ['1234567', $faker->name(), $faker->streetAddress(), '', 'S0000001', 'Z01-000004', '2026-05-01', 'ACTIVO'], // DNI invalido (7 digitos)
        ];

        $filaIndice = 2;
        foreach ($filas as $fila) {
            $sheet->setCellValueExplicit("A{$filaIndice}", $fila[0], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$filaIndice}", $fila[1]);
            $sheet->setCellValue("C{$filaIndice}", $fila[2]);
            $sheet->setCellValueExplicit("D{$filaIndice}", $fila[3], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$filaIndice}", $fila[4], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$filaIndice}", $fila[5], DataType::TYPE_STRING);
            $sheet->setCellValue("G{$filaIndice}", $fila[6]);
            $sheet->setCellValue("H{$filaIndice}", $fila[7]);
            $filaIndice++;
        }

        $rutaTemp = sys_get_temp_dir() . '/cartera_cp22_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($rutaTemp);

        try {
            $this->artisan('cartera:importar', ['archivo' => $rutaTemp])
                ->assertExitCode(0);

            $this->assertDatabaseCount('clientes', 2);
            $this->assertDatabaseHas('clientes', ['dni' => $dniUno]);
            $this->assertDatabaseHas('clientes', ['dni' => $dniDos]);

            $this->assertDatabaseCount('contratos', 2);
            $this->assertDatabaseHas('contratos', [
                'codigo_contrato' => 'Z01-000001',
                'estado' => 'activo',
            ]);
            $this->assertDatabaseHas('contratos', [
                'codigo_contrato' => 'Z01-000002',
                'estado' => 'suspendido',
            ]);

            // Repetibilidad: re-ejecutar no duplica clientes ni contratos.
            $this->artisan('cartera:importar', ['archivo' => $rutaTemp])
                ->assertExitCode(0);

            $this->assertDatabaseCount('clientes', 2);
            $this->assertDatabaseCount('contratos', 2);
            $this->assertFileExists(storage_path('cartera_rechazados.csv'));
        } finally {
            @unlink($rutaTemp);
        }
    }
}
