<?php

namespace Tests\Unit;

use App\Services\CatalogoClasificador;
use PHPUnit\Framework\TestCase;

class CatalogoClasificadorTest extends TestCase
{
    public function test_clasifica_tipos_segmentos_y_velocidad_del_catalogo(): void
    {
        $clasificador = new CatalogoClasificador();

        $this->assertSame('cargo_unico', $clasificador->clasificarTipo('RECONEXIÓN'));
        $this->assertSame('cargo_unico', $clasificador->clasificarTipo('INSTALACION DE FIBRA'));
        $this->assertSame('equipo', $clasificador->clasificarTipo('ONU GEPON DUAL BAND'));
        $this->assertSame('equipo', $clasificador->clasificarTipo('VENTA DE EQUIPOS ROUTER'));
        $this->assertSame('plan_combo', $clasificador->clasificarTipo('TELEVISION POR CABLE + INTERNET DE 60 MBPS'));
        $this->assertSame('plan_tv', $clasificador->clasificarTipo('TELEVISION POR CABLE BASICO'));
        $this->assertSame('plan_internet', $clasificador->clasificarTipo('INTERNET DE 100 MBPS EMPRESARIAL'));

        $this->assertSame('empresarial', $clasificador->detectarSegmento('INTERNET 100 MBPS EMPRESARIAL', 'plan_internet'));
        $this->assertSame('comercial', $clasificador->detectarSegmento('INTERNET 80 MBPS -COMERCIAL', 'plan_internet'));
        $this->assertSame('residencial', $clasificador->detectarSegmento('INTERNET 40 MBPS', 'plan_internet'));
        $this->assertNull($clasificador->detectarSegmento('RECONEXION', 'cargo_unico'));

        $this->assertSame(60, $clasificador->extraerVelocidadMbps('TELEVISION POR CABLE + INTERNET DE 60 MBPS', 'plan_combo'));
        $this->assertNull($clasificador->extraerVelocidadMbps('TELEVISION POR CABLE', 'plan_tv'));
    }
}

