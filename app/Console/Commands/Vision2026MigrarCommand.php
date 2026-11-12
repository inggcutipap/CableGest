<?php

namespace App\Console\Commands;

use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Services\CatalogoClasificador;
use App\Support\MigracionHelper;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PDO;

class Vision2026MigrarCommand extends Command
{
    protected $signature = 'vision2026:migrar
                            {--host= : Host de la base vision2026 (o variable LEGACY_DB_HOST)}
                            {--port=3306 : Puerto de la base vision2026 (o variable LEGACY_DB_PORT)}
                            {--database=vision2026 : Nombre de la base origen (o variable LEGACY_DB_NAME)}
                            {--username= : Usuario de solo lectura (o variable LEGACY_DB_USER)}
                            {--password= : Clave de conexion (o variable LEGACY_DB_PASS)}
                            {--solo= : Limita la migracion a una etapa: base o pagos}';

    protected $description = 'Migra catalogo (mitm), clientes (ccte), contratos (regcontratos) y pagos (regrecibos) desde vision2026 en modo solo lectura.';

    public function handle(CatalogoClasificador $clasificador): int
    {
        $host = trim((string) ($this->option('host') ?: env('LEGACY_DB_HOST', '')));
        $port = trim((string) ($this->option('port') ?: env('LEGACY_DB_PORT', '3306')));
        $database = trim((string) ($this->option('database') ?: env('LEGACY_DB_NAME', 'vision2026')));
        $username = trim((string) ($this->option('username') ?: env('LEGACY_DB_USER', '')));
        $password = (string) ($this->option('password') ?: env('LEGACY_DB_PASS', ''));

        if ($host === '' || $username === '' || $password === '') {
            $this->error('Debes indicar credenciales de lectura mediante opciones o variables LEGACY_DB_*.');

            return self::FAILURE;
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $solo = trim((string) ($this->option('solo') ?? ''));

        $rechazados = [];
        $resumen = Model::withoutEvents(function () use ($pdo, $clasificador, $solo, &$rechazados): array {
            $resultado = [];

            if ($solo === '' || $solo === 'base') {
                $resultado['catalogo'] = $this->migrarCatalogo($pdo, $clasificador, $rechazados);
                $resultado['clientes'] = $this->migrarClientes($pdo, $rechazados);
                $resultado['contratos'] = $this->migrarContratos($pdo, $rechazados);
            }

            if ($solo === '' || $solo === 'pagos') {
                $resultado['pagos'] = $this->migrarPagos($pdo, $rechazados);
            }

            return $resultado;
        });

        $rutaDuplicados = $clasificador->generarReporteDuplicados();
        $filasRechazos = array_map(
            static fn (array $fila): array => [$fila['tabla'], $fila['referencia'], $fila['motivo']],
            $rechazados
        );
        $rutaRechazos = MigracionHelper::escribirCsv(
            'vision2026_rechazados.csv',
            ['tabla', 'referencia', 'motivo'],
            $filasRechazos
        );

        if (isset($resumen['catalogo'])) {
            $this->info("Catalogo (mitm): {$resumen['catalogo']['procesados']} procesados.");
            $this->info("Clientes (ccte): {$resumen['clientes']['procesados']} validos procesados, {$resumen['clientes']['rechazados']} rechazados.");
            $this->info("Contratos (regcontratos): {$resumen['contratos']['procesados']} procesados, {$resumen['contratos']['rechazados']} rechazados.");
        }

        if (isset($resumen['pagos'])) {
            $this->info("Pagos (regrecibos): {$resumen['pagos']['procesados']} insertados, {$resumen['pagos']['omitidos']} ya existentes/omitidos, {$resumen['pagos']['rechazados']} rechazados.");
        }

        $this->line("Reporte de duplicados de catalogo: {$rutaDuplicados}");
        $this->line("Reporte de registros rechazados: {$rutaRechazos}");

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, string>> $rechazados
     * @return array<string, int>
     */
    private function migrarCatalogo(PDO $pdo, CatalogoClasificador $clasificador, array &$rechazados): array
    {
        $filas = $pdo->query('SELECT codigo, descri, preven, lin FROM mitm ORDER BY codigo')->fetchAll();
        $procesados = 0;

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $nombre = trim((string) ($fila['descri'] ?? ''));
            $linea = strtoupper(trim((string) ($fila['lin'] ?? '')));

            if ($codigo === '' || $nombre === '') {
                $rechazados[] = [
                    'tabla' => 'mitm',
                    'referencia' => $codigo !== '' ? $codigo : '(vacio)',
                    'motivo' => 'Codigo o descripcion vacia.',
                ];
                continue;
            }

            $tipo = $this->clasificarCatalogoLegacy($linea, $nombre, $clasificador);
            $segmento = $clasificador->detectarSegmento($nombre, $tipo);
            $velocidad = $clasificador->extraerVelocidadMbps($nombre, $tipo);
            $precio = number_format(round((float) ($fila['preven'] ?? 0), 2), 2, '.', '');
            $activo = $codigo !== '.';

            CatalogoServicio::updateOrCreate(
                ['codigo_interno' => mb_substr($codigo, 0, 10)],
                [
                    'nombre' => mb_substr($nombre, 0, 150),
                    'tipo' => $tipo,
                    'segmento' => $segmento,
                    'velocidad_mbps' => $velocidad,
                    'precio' => $precio,
                    'activo' => $activo,
                ]
            );
            $procesados++;
        }

        return ['procesados' => $procesados];
    }

    private function clasificarCatalogoLegacy(string $linea, string $nombre, CatalogoClasificador $clasificador): string
    {
        return match ($linea) {
            'CAB' => 'plan_tv',
            'DUO' => 'plan_combo',
            'INT' => str_contains(strtoupper($nombre), 'INSTALACION') ? 'cargo_unico' : 'plan_internet',
            '003', '006', '007' => 'equipo',
            'SRV' => 'cargo_unico',
            default => $clasificador->clasificarTipo($nombre),
        };
    }

    /**
     * @param array<int, array<string, string>> $rechazados
     * @return array<string, int>
     */
    private function migrarClientes(PDO $pdo, array &$rechazados): array
    {
        $stmt = $pdo->query(
            'SELECT codigo, identificador, nombre, apePat, apeMat, nomb1, nomb2, direcc, telefo, movil, tdate, fecha2 FROM ccte ORDER BY ID'
        );

        $ahora = now()->toDateTimeString();
        $lote = [];
        $procesados = 0;
        $conteoRechazos = 0;

        while (($fila = $stmt->fetch()) !== false) {
            $dni = trim((string) ($fila['codigo'] ?? ''));

            if (preg_match('/^\d{8}$/', $dni) !== 1) {
                $conteoRechazos++;
                $rechazados[] = [
                    'tabla' => 'ccte',
                    'referencia' => 'identificador=' . trim((string) ($fila['identificador'] ?? '')) . ',len=' . strlen($dni),
                    'motivo' => 'Identificador distinto de DNI numerico de 8 digitos.',
                ];
                continue;
            }

            $nombres = trim((string) ($fila['nombre'] ?? ''));
            if ($nombres === '') {
                $partes = array_filter([
                    trim((string) ($fila['apePat'] ?? '')),
                    trim((string) ($fila['apeMat'] ?? '')),
                    trim((string) ($fila['nomb1'] ?? '')),
                    trim((string) ($fila['nomb2'] ?? '')),
                ]);
                $nombres = trim(implode(' ', $partes));
            }

            if ($nombres === '') {
                $conteoRechazos++;
                $rechazados[] = [
                    'tabla' => 'ccte',
                    'referencia' => 'dni_valido_sin_nombre',
                    'motivo' => 'Cliente sin nombre registrado.',
                ];
                continue;
            }

            $direccion = trim((string) ($fila['direcc'] ?? ''));
            $telefono = trim((string) ($fila['telefo'] ?? ''));
            if ($telefono === '' || $telefono === '0') {
                $telefono = trim((string) ($fila['movil'] ?? ''));
            }

            $fecha = MigracionHelper::normalizarFecha($fila['tdate'] ?? null, $fila['fecha2'] ?? null);

            $lote[] = [
                'dni' => $dni,
                'nombres' => mb_substr($nombres, 0, 150),
                'direccion' => mb_substr($direccion !== '' ? $direccion : 'Sin direccion registrada', 0, 200),
                'telefono' => $telefono !== '' && $telefono !== '0' ? mb_substr($telefono, 0, 20) : null,
                'fecha_registro' => $fecha,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $procesados++;

            if (count($lote) >= 500) {
                DB::table('clientes')->upsert($lote, ['dni'], ['nombres', 'direccion', 'telefono', 'fecha_registro', 'updated_at']);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('clientes')->upsert($lote, ['dni'], ['nombres', 'direccion', 'telefono', 'fecha_registro', 'updated_at']);
        }

        return ['procesados' => $procesados, 'rechazados' => $conteoRechazos];
    }

    /**
     * @param array<int, array<string, string>> $rechazados
     * @return array<string, int>
     */
    private function migrarContratos(PDO $pdo, array &$rechazados): array
    {
        $clientePorDni = Cliente::query()->pluck('id', 'dni');
        $catalogoPorCodigo = CatalogoServicio::query()->pluck('id', 'codigo_interno');

        $stmt = $pdo->query(
            'SELECT talm, tnumcontrato, tprocli, tcodigo, tfecini, tfectra, tdate, testado FROM regcontratos ORDER BY talm, tnumcontrato'
        );

        $ahora = now()->toDateTimeString();
        $lote = [];
        $procesados = 0;
        $conteoRechazos = 0;

        while (($fila = $stmt->fetch()) !== false) {
            $codigoContrato = trim((string) ($fila['talm'] ?? '')) . '-' . trim((string) ($fila['tnumcontrato'] ?? ''));
            $dni = trim((string) ($fila['tprocli'] ?? ''));
            $codigoServicio = trim((string) ($fila['tcodigo'] ?? ''));

            $clienteId = $clientePorDni->get($dni);
            if ($clienteId === null) {
                $conteoRechazos++;
                $rechazados[] = [
                    'tabla' => 'regcontratos',
                    'referencia' => $codigoContrato,
                    'motivo' => 'Cliente origen omitido por no tener DNI de 8 digitos.',
                ];
                continue;
            }

            $catalogoId = $catalogoPorCodigo->get($codigoServicio);
            if ($catalogoId === null) {
                $conteoRechazos++;
                $rechazados[] = [
                    'tabla' => 'regcontratos',
                    'referencia' => $codigoContrato,
                    'motivo' => "Codigo de servicio no encontrado ({$codigoServicio}).",
                ];
                continue;
            }

            $fechaInicio = MigracionHelper::normalizarFecha(
                $fila['tfecini'] ?? null,
                $fila['tfectra'] ?? ($fila['tdate'] ?? null)
            );
            $estado = MigracionHelper::mapearEstadoContrato((string) ($fila['testado'] ?? 'ACTIVO'));

            $lote[] = [
                'codigo_contrato' => mb_substr($codigoContrato, 0, 20),
                'cliente_id' => $clienteId,
                'catalogo_id' => $catalogoId,
                'venta_detalle_id' => null,
                'fecha_inicio' => $fechaInicio,
                'estado' => $estado,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $procesados++;

            if (count($lote) >= 500) {
                DB::table('contratos')->upsert(
                    $lote,
                    ['codigo_contrato'],
                    ['cliente_id', 'catalogo_id', 'fecha_inicio', 'estado', 'updated_at']
                );
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('contratos')->upsert(
                $lote,
                ['codigo_contrato'],
                ['cliente_id', 'catalogo_id', 'fecha_inicio', 'estado', 'updated_at']
            );
        }

        return ['procesados' => $procesados, 'rechazados' => $conteoRechazos];
    }

    /**
     * Migra recibos PEN/AMR de contratos activos y pagos CAN de 2026.
     *
     * @param array<int, array<string, string>> $rechazados
     * @return array<string, int>
     */
    private function migrarPagos(PDO $pdo, array &$rechazados): array
    {
        $contratoPorCodigo = Contrato::query()->pluck('id', 'codigo_contrato');

        $periodosExistentes = [];
        foreach (Pago::query()->whereNotNull('periodo')->get(['contrato_id', 'periodo']) as $pago) {
            $periodosExistentes[$pago->contrato_id . '|' . $pago->periodo] = true;
        }

        $recibosExistentes = [];
        foreach (Pago::query()->where('observaciones', 'like', 'Recibo %')->get(['contrato_id', 'observaciones']) as $pago) {
            if (preg_match('/^Recibo\s+([^\s]+)/', (string) $pago->observaciones, $coincidencia) === 1) {
                $recibosExistentes[$pago->contrato_id . '|' . $coincidencia[1]] = true;
            }
        }

        $sql = "SELECT r.talm, r.tnumcontrato, r.tnumrecibo, r.tfectra, r.tfecven, r.tsit, "
            . "r.ttotal, r.tsaldo, r.tdescripcion, r.tupdate "
            . "FROM regrecibos r "
            . "INNER JOIN regcontratos rc ON r.talm = rc.talm AND r.tnumcontrato = rc.tnumcontrato "
            . "WHERE (r.tsit IN ('PEN', 'AMR') AND rc.testado = 'ACTIVO') "
            . "OR (r.tsit = 'CAN' AND r.tfectra >= '2026-01-01') "
            . "ORDER BY r.talm, r.tnumcontrato, r.tfectra, r.tnumrecibo";

        $stmt = $pdo->query($sql);
        $hoy = now()->toDateString();
        $ahora = now()->toDateTimeString();
        $lote = [];
        $procesados = 0;
        $omitidos = 0;
        $conteoRechazos = 0;

        while (($fila = $stmt->fetch()) !== false) {
            $codigoContrato = trim((string) ($fila['talm'] ?? '')) . '-' . trim((string) ($fila['tnumcontrato'] ?? ''));
            $numRecibo = trim((string) ($fila['tnumrecibo'] ?? ''));

            $contratoId = $contratoPorCodigo->get($codigoContrato);
            if ($contratoId === null) {
                $conteoRechazos++;
                $rechazados[] = [
                    'tabla' => 'regrecibos',
                    'referencia' => $codigoContrato . '/' . $numRecibo,
                    'motivo' => 'Contrato no migrado previamente.',
                ];
                continue;
            }

            if ($numRecibo !== '' && isset($recibosExistentes[$contratoId . '|' . $numRecibo])) {
                $omitidos++;
                continue;
            }

            $tsit = strtoupper(trim((string) ($fila['tsit'] ?? 'PEN')));
            $montoBruto = $tsit === 'CAN'
                ? (float) ($fila['ttotal'] ?? 0)
                : ((float) ($fila['tsaldo'] ?? 0) > 0 ? (float) $fila['tsaldo'] : (float) ($fila['ttotal'] ?? 0));

            if ($montoBruto <= 0) {
                $omitidos++;
                continue;
            }

            $periodo = null;
            if (preg_match('/^(\d{2})(\d{2})/', $numRecibo, $coincidencia) === 1) {
                $candidatoPeriodo = '20' . $coincidencia[1] . '-' . $coincidencia[2];
                $clavePeriodo = $contratoId . '|' . $candidatoPeriodo;
                if (! isset($periodosExistentes[$clavePeriodo])) {
                    $periodo = $candidatoPeriodo;
                    $periodosExistentes[$clavePeriodo] = true;
                }
            }

            if ($numRecibo !== '') {
                $recibosExistentes[$contratoId . '|' . $numRecibo] = true;
            }

            $fechaVencimiento = MigracionHelper::normalizarFecha($fila['tfecven'] ?? null, $fila['tfectra'] ?? null, $hoy);

            if ($tsit === 'CAN') {
                $estado = 'pagado';
                $fechaPago = MigracionHelper::normalizarFecha($fila['tupdate'] ?? null, $fila['tfectra'] ?? null, $fechaVencimiento);
            } else {
                $estado = $fechaVencimiento < $hoy ? 'vencido' : 'pendiente';
                $fechaPago = null;
            }

            $descripcion = trim((string) ($fila['tdescripcion'] ?? ''));
            $observaciones = trim('Recibo ' . $numRecibo . ($descripcion !== '' ? " - {$descripcion}" : ''));

            $lote[] = [
                'contrato_id' => $contratoId,
                'periodo' => $periodo,
                'monto' => number_format(round($montoBruto, 2), 2, '.', ''),
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'usuario_cobro_id' => null,
                'metodo_pago' => $estado === 'pagado' ? 'legacy_vb6' : null,
                'observaciones' => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $procesados++;

            if (count($lote) >= 500) {
                DB::table('pagos')->insert($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('pagos')->insert($lote);
        }

        return [
            'procesados' => $procesados,
            'omitidos' => $omitidos,
            'rechazados' => $conteoRechazos,
        ];
    }
}
