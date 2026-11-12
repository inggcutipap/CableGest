<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reclamo;
use App\Models\Usuario;
use App\Models\Venta;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint4Test extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConToken(string $rol = 'operador', bool $autenticar = true): array
    {
        $faker = FakerFactory::create('es_PE');

        $usuario = Usuario::create([
            'nombre' => $faker->name(),
            'correo' => $faker->unique()->safeEmail(),
            'password' => 'ClavePrueba123!',
            'rol' => $rol,
            'activo' => true,
        ]);

        return [$usuario, $autenticar ? (string) auth('api')->login($usuario) : ''];
    }

    private function crearItemEquipo(string $nombre = 'ONU GPON DUAL BAND'): CatalogoServicio
    {
        $faker = FakerFactory::create('es_PE');

        return CatalogoServicio::create([
            'codigo_interno' => 'A' . $faker->unique()->numerify('#######'),
            'nombre' => $nombre,
            'tipo' => 'equipo',
            'precio' => '80.00',
            'activo' => true,
        ]);
    }

    private function crearContratoActivo(): array
    {
        $faker = FakerFactory::create('es_PE');

        $cliente = Cliente::create([
            'nombres' => $faker->name(),
            'dni' => $faker->unique()->numerify('########'),
            'direccion' => $faker->streetAddress(),
            'telefono' => $faker->numerify('9########'),
            'fecha_registro' => now()->toDateString(),
        ]);

        $plan = CatalogoServicio::create([
            'codigo_interno' => 'S' . $faker->unique()->numerify('#######'),
            'nombre' => 'SOLO INTERNET 60 MG',
            'tipo' => 'plan_internet',
            'segmento' => 'residencial',
            'velocidad_mbps' => 60,
            'precio' => '60.00',
            'activo' => true,
        ]);

        $contrato = Contrato::create([
            'codigo_contrato' => $faker->unique()->numerify('########'),
            'cliente_id' => $cliente->id,
            'catalogo_id' => $plan->id,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        return [$cliente, $plan, $contrato];
    }

    public function test_cp13_entrada_equipo_con_serie_nueva_crea_disponible_y_movimiento(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $catalogoEquipo = $this->crearItemEquipo();

        $respuesta = $this->withToken($token)->postJson('/api/equipos', [
            'catalogo_id' => $catalogoEquipo->id,
            'marca' => 'Huawei',
            'serie' => 'sn-onu-2026-001',
            'motivo' => 'Compra lote noviembre',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.serie', 'SN-ONU-2026-001')
            ->assertJsonPath('data.estado', 'disponible')
            ->assertJsonPath('data.marca', 'Huawei');

        $equipoId = (int) $respuesta->json('data.id');

        $this->assertDatabaseHas('equipos', [
            'id' => $equipoId,
            'serie' => 'SN-ONU-2026-001',
            'estado' => 'disponible',
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $usuario->id,
            'tipo_movimiento' => 'entrada',
            'motivo' => 'Compra lote noviembre',
        ]);
    }

    public function test_cp14_equipo_con_serie_repetida_es_rechazado(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        $catalogoEquipo = $this->crearItemEquipo();

        Equipo::create([
            'catalogo_id' => $catalogoEquipo->id,
            'marca' => 'TP-Link',
            'serie' => 'SN-REP-001',
            'estado' => 'disponible',
        ]);

        $respuesta = $this->withToken($token)->postJson('/api/equipos', [
            'catalogo_id' => $catalogoEquipo->id,
            'marca' => 'TP-Link',
            'serie' => 'sn-rep-001',
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['serie']);
    }

    public function test_cp15_instalar_equipo_en_contrato_pasa_a_instalado_y_descuenta_stock(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $catalogoEquipo = $this->crearItemEquipo();
        [, , $contrato] = $this->crearContratoActivo();

        $equipo = Equipo::create([
            'catalogo_id' => $catalogoEquipo->id,
            'marca' => 'ZTE',
            'serie' => 'SN-INST-001',
            'estado' => 'disponible',
        ]);

        $respuesta = $this->withToken($token)->postJson("/api/equipos/{$equipo->id}/instalar", [
            'contrato_id' => $contrato->id,
            'fecha_instalacion' => '2026-11-12',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.estado', 'instalado');

        $this->assertDatabaseHas('instalaciones', [
            'contrato_id' => $contrato->id,
            'equipo_id' => $equipo->id,
            'usuario_id' => $usuario->id,
            'activo' => 1,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'equipo_id' => $equipo->id,
            'tipo_movimiento' => 'salida',
        ]);

        $this->withToken($token)
            ->getJson('/api/inventario/stock')
            ->assertOk()
            ->assertJsonPath('data.0.stock_disponible', 0)
            ->assertJsonPath('data.0.instalados', 1);
    }

    public function test_cp16_consultar_stock_por_tipo_cuenta_solo_equipos_disponibles(): void
    {
        [, $token] = $this->crearUsuarioConToken();
        $itemA = $this->crearItemEquipo('ROUTER TP-LINK');
        $itemB = $this->crearItemEquipo('ONU FIBRA');

        Equipo::create(['catalogo_id' => $itemA->id, 'marca' => 'TP-Link', 'serie' => 'A-01', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $itemA->id, 'marca' => 'TP-Link', 'serie' => 'A-02', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $itemA->id, 'marca' => 'TP-Link', 'serie' => 'A-03', 'estado' => 'instalado']);
        Equipo::create(['catalogo_id' => $itemB->id, 'marca' => 'Huawei', 'serie' => 'B-01', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $itemB->id, 'marca' => 'Huawei', 'serie' => 'B-02', 'estado' => 'baja']);

        $respuesta = $this->withToken($token)->getJson('/api/inventario/stock');

        $respuesta->assertOk()
            ->assertJsonPath('meta.total_disponible', 3)
            ->assertJsonPath('meta.total_instalado', 1);

        $porId = collect($respuesta->json('data'))->keyBy('id');
        $this->assertSame(2, $porId[$itemA->id]['stock_disponible']);
        $this->assertSame(1, $porId[$itemB->id]['stock_disponible']);
    }

    public function test_cp17_exactitud_conteo_fisico_calcula_coincidentes_sobre_verificados(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $item = $this->crearItemEquipo();

        // 3 equipos disponibles en sistema: S1, S2, S3 (S4 esta instalado, no disponible).
        Equipo::create(['catalogo_id' => $item->id, 'marca' => 'Huawei', 'serie' => 'S1', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $item->id, 'marca' => 'Huawei', 'serie' => 'S2', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $item->id, 'marca' => 'Huawei', 'serie' => 'S3', 'estado' => 'disponible']);
        Equipo::create(['catalogo_id' => $item->id, 'marca' => 'Huawei', 'serie' => 'S4', 'estado' => 'instalado']);

        // Conteo fisico: S1, S2 y S99 (sobrante).
        // Coincidentes = 2 (S1, S2); Union verificados = 4 (S1, S2, S3, S99) -> 50.00%
        $respuesta = $this->withToken($token)->postJson('/api/inventario/exactitud', [
            'series' => ['s1', 'S2', 'S99'],
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.total_sistema', 3)
            ->assertJsonPath('data.total_fisico', 3)
            ->assertJsonPath('data.coincidentes', 2)
            ->assertJsonPath('data.verificados', 4)
            ->assertJsonPath('data.exactitud', '50.00')
            ->assertJsonPath('data.discrepancias.faltantes.0.serie', 'S3')
            ->assertJsonPath('data.discrepancias.sobrantes.0.serie', 'S99');

        $this->assertDatabaseHas('conteos_inventario', [
            'usuario_id' => $usuario->id,
            'coincidentes' => 2,
            'verificados' => 4,
            'exactitud' => '50.00',
        ]);
    }

    public function test_cp18_registrar_reclamo_y_asignar_tecnico(): void
    {
        [$operador, $token] = $this->crearUsuarioConToken('operador');
        [$tecnico] = $this->crearUsuarioConToken('tecnico', false);
        [$cliente, , $contrato] = $this->crearContratoActivo();

        $creacion = $this->withToken($token)->postJson('/api/reclamos', [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'Sin acceso a internet desde la mañana',
        ]);

        $creacion->assertStatus(201)
            ->assertJsonPath('data.estado', 'abierto')
            ->assertJsonPath('data.diagnostico', 'pendiente')
            ->assertJsonPath('data.usuario_registro_id', $operador->id);

        $reclamoId = (int) $creacion->json('data.id');

        $asignacion = $this->withToken($token)->patchJson("/api/reclamos/{$reclamoId}/asignar", [
            'tecnico_id' => $tecnico->id,
        ]);

        $asignacion->assertOk()
            ->assertJsonPath('data.tecnico_id', $tecnico->id)
            ->assertJsonPath('data.estado', 'en_proceso');
    }

    public function test_cp19_registrar_diagnostico_y_cerrar_reclamo(): void
    {
        [$operador, $token] = $this->crearUsuarioConToken('operador');
        [$tecnico] = $this->crearUsuarioConToken('tecnico', false);
        [$cliente, , $contrato] = $this->crearContratoActivo();

        $reclamo = Reclamo::create([
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'usuario_registro_id' => $operador->id,
            'tecnico_id' => $tecnico->id,
            'motivo' => 'Luz LOS parpadeando',
            'diagnostico' => 'pendiente',
            'estado' => 'en_proceso',
            'fecha_registro' => '2026-11-12',
        ]);

        $respuesta = $this->withToken($token)->patchJson("/api/reclamos/{$reclamo->id}/diagnostico", [
            'diagnostico' => 'falla_confirmada',
            'observaciones' => 'Patchcord óptico dañado, reemplazado en sitio',
            'fecha_atencion' => '2026-11-12',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.estado', 'resuelto')
            ->assertJsonPath('data.diagnostico', 'falla_confirmada')
            ->assertJsonPath('data.fecha_atencion', '2026-11-12');

        $this->assertDatabaseHas('reclamos', [
            'id' => $reclamo->id,
            'estado' => 'resuelto',
            'diagnostico' => 'falla_confirmada',
        ]);
    }

    public function test_cp21_dashboard_indicadores_coinciden_con_consultas_directas(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        [$cliente, $plan, $contrato] = $this->crearContratoActivo();
        $equipoItem = $this->crearItemEquipo('SPLITTER 2 VIAS');

        // Venta registrada + venta anulada (no debe entrar al top 5).
        $ventaActiva = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_venta' => now()->toDateString(),
            'monto_total' => '220.00',
            'estado' => 'registrada',
        ]);
        $ventaActiva->detalles()->create([
            'catalogo_id' => $plan->id,
            'cantidad' => 1,
            'precio_unitario' => '60.00',
            'subtotal' => '60.00',
        ]);
        $ventaActiva->detalles()->create([
            'catalogo_id' => $equipoItem->id,
            'cantidad' => 2,
            'precio_unitario' => '80.00',
            'subtotal' => '160.00',
        ]);

        $ventaAnulada = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_venta' => now()->toDateString(),
            'monto_total' => '400.00',
            'estado' => 'anulada',
        ]);
        $ventaAnulada->detalles()->create([
            'catalogo_id' => $equipoItem->id,
            'cantidad' => 5,
            'precio_unitario' => '80.00',
            'subtotal' => '400.00',
        ]);

        // Pagos: 1 pagado este mes (60.00), 1 vencido -> 50% morosidad.
        Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-11',
            'monto' => '60.00',
            'fecha_vencimiento' => now()->endOfMonth()->toDateString(),
            'fecha_pago' => now()->toDateString(),
            'estado' => 'pagado',
            'usuario_cobro_id' => $usuario->id,
        ]);
        $pagoVencido = Pago::create([
            'contrato_id' => $contrato->id,
            'periodo' => '2026-10',
            'monto' => '60.00',
            'fecha_vencimiento' => now()->subDays(10)->toDateString(),
            'estado' => 'vencido',
        ]);
        Notificacion::create([
            'pago_id' => $pagoVencido->id,
            'tipo' => 'vencimiento',
            'mensaje' => 'Alerta prueba',
            'estado' => 'pendiente',
            'fecha_generacion' => now(),
        ]);

        // Reclamos: 1 abierto, 1 resuelto.
        Reclamo::create([
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'usuario_registro_id' => $usuario->id,
            'motivo' => 'Intermitencia',
            'diagnostico' => 'pendiente',
            'estado' => 'abierto',
            'fecha_registro' => now()->toDateString(),
        ]);
        Reclamo::create([
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'usuario_registro_id' => $usuario->id,
            'motivo' => 'Cambio de clave WiFi',
            'diagnostico' => 'sin_falla',
            'estado' => 'resuelto',
            'fecha_registro' => now()->toDateString(),
            'fecha_atencion' => now()->toDateString(),
            'observaciones' => 'Resuelto remotamente',
        ]);

        // Inventario + conteo fisico.
        Equipo::create([
            'catalogo_id' => $equipoItem->id,
            'marca' => 'Generico',
            'serie' => 'EQ-DASH-01',
            'estado' => 'disponible',
        ]);
        $this->withToken($token)->postJson('/api/inventario/exactitud', [
            'series' => ['EQ-DASH-01'],
        ])->assertStatus(201);

        $respuesta = $this->withToken($token)->getJson('/api/dashboard');

        $respuesta->assertOk()
            ->assertJsonPath('data.clientes_activos', Contrato::where('estado', 'activo')->count())
            ->assertJsonPath('data.ingresos_mes', 60)
            ->assertJsonPath('data.tasa_morosidad', 50)
            ->assertJsonPath('data.reclamos_abiertos', 1)
            ->assertJsonPath('data.reclamos_resueltos', 1)
            ->assertJsonPath('data.exactitud_inventario', 100)
            ->assertJsonPath('data.stock_disponible', 1)
            ->assertJsonPath('data.alertas_pendientes', 1)
            ->assertJsonPath('data.items_mas_vendidos.0.catalogo_id', $equipoItem->id)
            ->assertJsonPath('data.items_mas_vendidos.0.cantidad_total', 2);
    }

    public function test_auditoria_registra_equipos_y_reclamos_y_permite_consulta_solo_lectura(): void
    {
        [$usuario, $token] = $this->crearUsuarioConToken();
        $catalogoEquipo = $this->crearItemEquipo();
        [$cliente, , $contrato] = $this->crearContratoActivo();

        $eqResp = $this->withToken($token)->postJson('/api/equipos', [
            'catalogo_id' => $catalogoEquipo->id,
            'marca' => 'Huawei',
            'serie' => 'SN-AUD-01',
        ])->assertStatus(201);

        $recResp = $this->withToken($token)->postJson('/api/reclamos', [
            'cliente_id' => $cliente->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'Prueba auditoria',
        ])->assertStatus(201);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'crear',
            'tabla_afectada' => 'equipos',
            'registro_id' => (int) $eqResp->json('data.id'),
        ]);

        $this->assertDatabaseHas('auditoria', [
            'usuario_id' => $usuario->id,
            'accion' => 'crear',
            'tabla_afectada' => 'reclamos',
            'registro_id' => (int) $recResp->json('data.id'),
        ]);

        $this->withToken($token)
            ->getJson('/api/auditoria?tabla_afectada=equipos')
            ->assertOk()
            ->assertJsonPath('data.0.tabla_afectada', 'equipos');

        $this->assertGreaterThanOrEqual(2, Auditoria::count());
    }
}
