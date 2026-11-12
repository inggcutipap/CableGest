<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Usuario;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Sprint3Test extends TestCase
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

    private function crearContratoPrueba(string $estado = 'activo', string $precio = '50.00', ?Cliente $cliente = null): array
    {
        $faker = FakerFactory::create('es_PE');

        $cliente ??= Cliente::create([
            'nombres' => $faker->name(),
            'dni' => $faker->unique()->numerify('########'),
            'direccion' => $faker->streetAddress(),
            'telefono' => $faker->numerify('9########'),
            'fecha_registro' => '2026-10-01',
        ]);

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S' . $faker->unique()->numerify('#######'),
            'nombre' => 'PLAN INTERNET ' . $faker->numberBetween(30, 100) . ' MB',
            'tipo' => 'plan_internet',
            'segmento' => 'residencial',
            'velocidad_mbps' => 50,
            'precio' => $precio,
            'activo' => true,
        ]);

        $contrato = Contrato::create([
            'codigo_contrato' => $faker->unique()->numerify('########'),
            'cliente_id' => $cliente->id,
            'catalogo_id' => $plan->id,
            'fecha_inicio' => '2026-10-01',
            'estado' => $estado,
        ]);

        return [$cliente, $plan, $contrato];
    }

    public function test_cp09_registrar_pago_de_contrato_marca_pagado_con_fecha(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        [, , $contrato] = $this->crearContratoPrueba();

        $pago = Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-11',
            'monto' => '50.00',
            'fecha_vencimiento' => '2026-11-30',
            'estado' => 'pendiente',
        ]);

        $respuesta = $this->withToken($token)->postJson("/api/pagos/{$pago->id}/registrar", [
            'fecha_pago' => '2026-11-12',
            'metodo_pago' => 'yape',
            'observaciones' => 'Cobrado en caja',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.estado', 'pagado')
            ->assertJsonPath('data.fecha_pago', '2026-11-12')
            ->assertJsonPath('data.metodo_pago', 'yape')
            ->assertJsonPath('data.usuario_cobro_id', $usuario->id);

        $this->assertDatabaseHas('pagos', [
            'id' => $pago->id,
            'estado' => 'pagado',
            'metodo_pago' => 'yape',
            'usuario_cobro_id' => $usuario->id,
        ]);

        $segundoIntento = $this->withToken($token)->postJson("/api/pagos/{$pago->id}/registrar", [
            'metodo_pago' => 'efectivo',
        ]);

        $segundoIntento->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_cp10_dejar_vencer_pago_genera_vencido_y_notificacion_sin_duplicar(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        [, , $contrato] = $this->crearContratoPrueba();

        $pagoVencible = Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-10',
            'monto' => '60.00',
            'fecha_vencimiento' => '2026-10-31',
            'estado' => 'pendiente',
        ]);

        $pagoVigente = Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-11',
            'monto' => '60.00',
            'fecha_vencimiento' => '2026-11-30',
            'estado' => 'pendiente',
        ]);

        $this->artisan('pagos:marcar-vencidos', ['--fecha' => '2026-11-12'])
            ->assertExitCode(0);

        $this->artisan('pagos:marcar-vencidos', ['--fecha' => '2026-11-12'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('pagos', [
            'id' => $pagoVencible->id,
            'estado' => 'vencido',
        ]);
        $this->assertDatabaseHas('pagos', [
            'id' => $pagoVigente->id,
            'estado' => 'pendiente',
        ]);
        $this->assertSame(1, Notificacion::query()->where('pago_id', $pagoVencible->id)->count());

        $notificacion = Notificacion::query()->where('pago_id', $pagoVencible->id)->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/notificaciones?estado=pendiente')
            ->assertOk()
            ->assertJsonPath('meta.pendientes', 1)
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->patchJson("/api/notificaciones/{$notificacion->id}/vista")
            ->assertOk()
            ->assertJsonPath('data.estado', 'vista');

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->id,
            'estado' => 'vista',
        ]);
    }

    public function test_cp11_estado_cuenta_cliente_calcula_pagos_y_saldo_pendiente(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        [$cliente, , $contrato1] = $this->crearContratoPrueba('activo', '50.00');
        [, , $contrato2] = $this->crearContratoPrueba('activo', '80.00', $cliente);

        Pago::create([
            'contrato_id' => $contrato1->id,
            'periodo' => '2026-09',
            'monto' => '50.00',
            'fecha_vencimiento' => '2026-09-30',
            'fecha_pago' => '2026-09-20',
            'estado' => 'pagado',
            'metodo_pago' => 'efectivo',
        ]);

        Pago::create([
            'contrato_id' => $contrato1->id,
            'periodo' => '2026-10',
            'monto' => '50.00',
            'fecha_vencimiento' => '2026-10-31',
            'estado' => 'vencido',
        ]);

        Pago::create([
            'contrato_id' => $contrato2->id,
            'periodo' => '2026-11',
            'monto' => '80.00',
            'fecha_vencimiento' => '2026-11-30',
            'estado' => 'pendiente',
        ]);

        $respuesta = $this->withToken($token)->getJson("/api/clientes/{$cliente->id}/estado-cuenta");

        $respuesta->assertOk()
            ->assertJsonPath('data.cliente.id', $cliente->id)
            ->assertJsonPath('data.resumen.monto_pagado', 50)
            ->assertJsonPath('data.resumen.monto_vencido', 50)
            ->assertJsonPath('data.resumen.monto_pendiente', 80)
            ->assertJsonPath('data.resumen.saldo_pendiente', 130)
            ->assertJsonPath('data.resumen.conteo.pagado', 1)
            ->assertJsonPath('data.resumen.conteo.vencido', 1)
            ->assertJsonPath('data.resumen.conteo.pendiente', 1)
            ->assertJsonCount(3, 'data.pagos');
    }

    public function test_cp12_reporte_morosidad_lista_solo_contratos_con_pagos_vencidos(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        [, , $contratoMoroso] = $this->crearContratoPrueba('activo', '70.00');
        [, , $contratoAlDia] = $this->crearContratoPrueba('activo', '50.00');

        $fechaAntigua = now()->subDays(15)->toDateString();

        Pago::create([
            'contrato_id' => $contratoMoroso->id,
            'periodo' => '2026-09',
            'monto' => '70.00',
            'fecha_vencimiento' => $fechaAntigua,
            'estado' => 'vencido',
        ]);
        Pago::create([
            'contrato_id' => $contratoMoroso->id,
            'periodo' => '2026-10',
            'monto' => '70.00',
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'estado' => 'vencido',
        ]);
        Pago::create([
            'contrato_id' => $contratoAlDia->id,
            'periodo' => '2026-11',
            'monto' => '50.00',
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'estado' => 'pendiente',
        ]);

        $respuesta = $this->withToken($token)->getJson('/api/reportes/morosidad');

        $respuesta->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contrato_id', $contratoMoroso->id)
            ->assertJsonPath('data.0.pagos_vencidos_count', 2)
            ->assertJsonPath('data.0.monto_adeudado', 140)
            ->assertJsonPath('data.0.dias_vencidos', 15)
            ->assertJsonPath('meta.total_contratos_morosos', 1)
            ->assertJsonPath('meta.monto_total_adeudado', 140)
            ->assertJsonPath('meta.total_pagos_vencidos', 2);
    }

    public function test_cp20_registrar_y_vencer_pago_registra_auditoria(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        [, , $contrato] = $this->crearContratoPrueba();

        $creacion = $this->withToken($token)->postJson('/api/pagos', [
            'contrato_id' => $contrato->id,
            'periodo' => '2026-11',
            'monto' => 55.00,
            'fecha_vencimiento' => '2026-11-30',
        ]);

        $creacion->assertStatus(201);
        $pagoId = (int) $creacion->json('data.id');

        $this->withToken($token)->postJson("/api/pagos/{$pagoId}/registrar", [
            'fecha_pago' => '2026-11-12',
            'metodo_pago' => 'transferencia',
        ])->assertOk();

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'crear',
            'tabla_afectada' => 'pagos',
            'registro_id' => $pagoId,
        ]);

        $auditoriaCobro = Auditoria::query()
            ->where('tabla_afectada', 'pagos')
            ->where('registro_id', $pagoId)
            ->where('accion', 'actualizar')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditoriaCobro);
        $this->assertSame($usuario->id, $auditoriaCobro->usuario_id);
        $this->assertSame('pagado', $auditoriaCobro->detalle['despues']['estado'] ?? null);
    }

    public function test_generar_cobros_mensuales_respeta_config_y_no_duplica(): void
    {
        config(['cablegest.cobranza.dia_vencimiento' => 'fin_de_mes']);

        [, , $contratoActivo] = $this->crearContratoPrueba('activo', '65.00');
        [, , $contratoSuspendido] = $this->crearContratoPrueba('suspendido', '65.00');

        $this->artisan('pagos:generar-mes', ['--periodo' => '2026-11'])
            ->assertExitCode(0);

        $this->artisan('pagos:generar-mes', ['--periodo' => '2026-11'])
            ->assertExitCode(0);

        $this->assertSame(1, Pago::query()->where('contrato_id', $contratoActivo->id)->where('periodo', '2026-11')->count());
        $this->assertSame(0, Pago::query()->where('contrato_id', $contratoSuspendido->id)->count());

        $pago = Pago::query()->where('contrato_id', $contratoActivo->id)->where('periodo', '2026-11')->firstOrFail();
        $this->assertSame('2026-11-30', $pago->fecha_vencimiento?->format('Y-m-d'));
        $this->assertSame('65.00', (string) $pago->monto);
    }

    public function test_importar_recibos_excel_es_repetible_y_reporta_rechazos(): void
    {
        [, , $contrato] = $this->crearContratoPrueba('activo', '50.00');

        $ruta = tempnam(sys_get_temp_dir(), 'recibos_') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray([
            ['tnumcontrato', 'tnumrecibo', 'tsaldo', 'tfecven', 'tsit', 'tdescripcion'],
        ]);
        $hoja->setCellValueExplicit('A2', (string) $contrato->codigo_contrato, DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('B2', '2611-0001', DataType::TYPE_STRING);
        $hoja->setCellValue('C2', 50.00);
        $hoja->setCellValueExplicit('D2', '2026-11-30', DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('E2', 'PEN', DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('F2', 'Mensualidad noviembre', DataType::TYPE_STRING);

        $hoja->setCellValueExplicit('A3', '99999999', DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('B3', '2611-0002', DataType::TYPE_STRING);
        $hoja->setCellValue('C3', 50.00);
        $hoja->setCellValueExplicit('D3', '2026-11-30', DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('E3', 'PEN', DataType::TYPE_STRING);

        (new Xlsx($spreadsheet))->save($ruta);

        try {
            $this->artisan('recibos:importar', ['archivo' => $ruta])
                ->assertExitCode(0);
            $this->artisan('recibos:importar', ['archivo' => $ruta])
                ->assertExitCode(0);

            $this->assertSame(1, Pago::query()->where('contrato_id', $contrato->id)->count());
            $this->assertDatabaseHas('pagos', [
                'contrato_id' => $contrato->id,
                'periodo' => '2026-11',
                'monto' => '50.00',
                'estado' => 'pendiente',
            ]);

            $rutaRechazos = storage_path('recibos_rechazados.csv');
            $this->assertFileExists($rutaRechazos);
            $contenido = (string) file_get_contents($rutaRechazos);
            $this->assertStringContainsString('99999999', $contenido);
        } finally {
            @unlink($ruta);
        }
    }
}
