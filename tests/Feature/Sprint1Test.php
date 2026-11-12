<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Sprint1Test extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioAutenticado(): Usuario
    {
        $faker = FakerFactory::create('es_PE');

        return Usuario::create([
            'nombre' => $faker->name(),
            'correo' => $faker->unique()->safeEmail(),
            'password' => 'ClavePrueba123!',
            'rol' => 'operador',
            'activo' => true,
        ]);
    }

    private function obtenerTokenPara(Usuario $usuario): string
    {
        return (string) auth('api')->login($usuario);
    }

    public function test_cp01_iniciar_sesion_con_credenciales_validas(): void
    {
        $faker = FakerFactory::create('es_PE');
        $correo = $faker->unique()->safeEmail();

        Usuario::create([
            'nombre' => $faker->name(),
            'correo' => $correo,
            'password' => 'Secreta2026!',
            'rol' => 'administrador',
            'activo' => true,
        ]);

        $respuesta = $this->postJson('/api/login', [
            'correo' => $correo,
            'password' => 'Secreta2026!',
        ]);

        $respuesta->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                    'usuario' => ['id', 'nombre', 'correo', 'rol'],
                ],
                'message',
            ]);

        $token = $respuesta->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.correo', $correo);
    }

    public function test_cp02_iniciar_sesion_con_credenciales_invalidas(): void
    {
        $faker = FakerFactory::create('es_PE');
        $correo = $faker->unique()->safeEmail();

        Usuario::create([
            'nombre' => $faker->name(),
            'correo' => $correo,
            'password' => 'ClaveCorrecta123!',
            'rol' => 'operador',
            'activo' => true,
        ]);

        $respuesta = $this->postJson('/api/login', [
            'correo' => $correo,
            'password' => 'ClaveIncorrecta999',
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_cp03_pedir_recurso_protegido_sin_token(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/clientes')->assertStatus(401);
        $this->getJson('/api/catalogo')->assertStatus(401);
    }

    public function test_cp04_registrar_cliente_con_dni_nuevo(): void
    {
        $faker = FakerFactory::create('es_PE');
        $usuario = $this->crearUsuarioAutenticado();
        $token = $this->obtenerTokenPara($usuario);
        $dni = $faker->unique()->numerify('########');

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/clientes', [
                'nombres' => $faker->name(),
                'dni' => $dni,
                'direccion' => $faker->streetAddress(),
                'telefono' => $faker->numerify('9########'),
            ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('data.dni', $dni);

        $this->assertDatabaseHas('clientes', ['dni' => $dni]);
        $this->assertDatabaseHas('auditoria', [
            'tabla_afectada' => 'clientes',
            'accion' => 'crear',
            'usuario_id' => $usuario->id,
        ]);
    }

    public function test_cp05_registrar_cliente_con_dni_existente(): void
    {
        $faker = FakerFactory::create('es_PE');
        $usuario = $this->crearUsuarioAutenticado();
        $token = $this->obtenerTokenPara($usuario);
        $dni = $faker->unique()->numerify('########');

        Cliente::create([
            'nombres' => $faker->name(),
            'dni' => $dni,
            'direccion' => $faker->streetAddress(),
            'telefono' => $faker->numerify('9########'),
            'fecha_registro' => now()->toDateString(),
        ]);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/clientes', [
                'nombres' => $faker->name(),
                'dni' => $dni,
                'direccion' => $faker->streetAddress(),
                'telefono' => $faker->numerify('9########'),
            ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_cp06_desactivar_item_del_catalogo(): void
    {
        $faker = FakerFactory::create('es_PE');
        $usuario = $this->crearUsuarioAutenticado();
        $token = $this->obtenerTokenPara($usuario);

        $item = CatalogoServicio::create([
            'codigo_interno' => '00099',
            'nombre' => 'PLAN INTERNET DEMO 50 MBPS',
            'tipo' => 'plan_internet',
            'segmento' => 'residencial',
            'velocidad_mbps' => 50,
            'precio' => 80.00,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'nombres' => $faker->name(),
            'dni' => $faker->unique()->numerify('########'),
            'direccion' => $faker->streetAddress(),
            'telefono' => $faker->numerify('9########'),
            'fecha_registro' => now()->toDateString(),
        ]);

        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'fecha_venta' => now()->toDateString(),
            'monto_total' => 80.00,
            'estado' => 'registrada',
        ]);

        $detalle = VentaDetalle::create([
            'venta_id' => $venta->id,
            'catalogo_id' => $item->id,
            'cantidad' => 1,
            'precio_unitario' => 80.00,
            'subtotal' => 80.00,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/catalogo/{$item->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $activos = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/catalogo?activo=1')
            ->assertOk()
            ->json('data');

        $this->assertCount(0, array_filter($activos, fn (array $fila): bool => $fila['id'] === $item->id));
        $this->assertEquals('80.00', number_format((float) $detalle->fresh()->precio_unitario, 2, '.', ''));

        $this->assertTrue(
            Auditoria::where('tabla_afectada', 'catalogo_servicios')
                ->where('registro_id', $item->id)
                ->where('accion', 'actualizar')
                ->exists()
        );
    }

    public function test_cerrar_sesion_invalida_el_token_jwt(): void
    {
        $usuario = $this->crearUsuarioAutenticado();
        $token = $this->obtenerTokenPara($usuario);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_limite_de_intentos_de_inicio_de_sesion(): void
    {
        $faker = FakerFactory::create('es_PE');
        $correo = $faker->unique()->safeEmail();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'correo' => $correo,
                'password' => 'Incorrecta123',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'correo' => $correo,
            'password' => 'Incorrecta123',
        ])->assertStatus(429);
    }

    public function test_importar_catalogo_excel_conserva_ceros_clasifica_y_reporta_duplicados(): void
    {
        $rutaExcel = sys_get_temp_dir().'/catalogo_prueba_'.uniqid('', true).'.xlsx';
        $rutaCsv = storage_path('catalogo_duplicados.csv');

        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray(['internal_id', 'description', 'sale_unit_price'], null, 'A1');

        $filas = [
            ['00001', 'SERVICIO DE INTERNET DE 200 MBPS', 260.00],
            ['00004', 'RECONEXION', 10.00],
            ['00047', 'RECONEXION', 5.00],
            ['00012', 'TELEVISION POR CABLE + INTERNET DE 100 MBPS', 110.00],
            ['00028', 'INTERNET POR FIBRA OPTICA DE 80 MBPS -COMERCIAL', 120.00],
            ['00044', 'ONU GEPON DUAL BAND', 1.00],
        ];

        $filaActual = 2;
        foreach ($filas as $fila) {
            $hoja->setCellValueExplicit("A{$filaActual}", $fila[0], DataType::TYPE_STRING);
            $hoja->setCellValue("B{$filaActual}", $fila[1]);
            $hoja->setCellValue("C{$filaActual}", $fila[2]);
            $filaActual++;
        }

        (new Xlsx($spreadsheet))->save($rutaExcel);
        $csvPrevio = is_file($rutaCsv) ? (string) file_get_contents($rutaCsv) : null;

        try {
            $this->artisan('catalogo:importar', ['archivo' => $rutaExcel])
                ->assertSuccessful();

            // Ejecucion repetida no duplica registros.
            $this->artisan('catalogo:importar', ['archivo' => $rutaExcel])
                ->assertSuccessful();

            $this->assertDatabaseCount('catalogo_servicios', 6);
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00001',
                'tipo' => 'plan_internet',
                'segmento' => 'residencial',
                'velocidad_mbps' => 200,
                'activo' => 1,
            ]);
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00012',
                'tipo' => 'plan_combo',
                'velocidad_mbps' => 100,
            ]);
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00028',
                'tipo' => 'plan_internet',
                'segmento' => 'comercial',
                'velocidad_mbps' => 80,
            ]);
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00044',
                'tipo' => 'equipo',
                'segmento' => null,
            ]);
            // Los duplicados NO se desactivan automaticamente.
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00004',
                'tipo' => 'cargo_unico',
                'activo' => 1,
            ]);
            $this->assertDatabaseHas('catalogo_servicios', [
                'codigo_interno' => '00047',
                'tipo' => 'cargo_unico',
                'activo' => 1,
            ]);

            $this->assertFileExists($rutaCsv);
            $contenidoCsv = (string) file_get_contents($rutaCsv);
            $this->assertStringContainsString('00004', $contenidoCsv);
            $this->assertStringContainsString('00047', $contenidoCsv);
        } finally {
            if (is_file($rutaExcel)) {
                unlink($rutaExcel);
            }
            if ($csvPrevio !== null) {
                file_put_contents($rutaCsv, $csvPrevio);
            } elseif (is_file($rutaCsv)) {
                unlink($rutaCsv);
            }
        }
    }
}

