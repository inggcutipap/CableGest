<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * Crea el usuario administrador inicial sin duplicar registros.
     */
    public function run(): void
    {
        $claveInicial = env('ADMIN_SEED_PASSWORD', 'CableGest2026!');

        Usuario::updateOrCreate(
            ['correo' => 'admin@cablevision.pe'],
            [
                'nombre' => 'Administrador CableGest',
                'password' => $claveInicial,
                'rol' => 'administrador',
                'activo' => true,
            ]
        );

        Usuario::updateOrCreate(
            ['correo' => 'operador@cablevision.pe'],
            [
                'nombre' => 'Operador Atencion',
                'password' => $claveInicial,
                'rol' => 'operador',
                'activo' => true,
            ]
        );

        Usuario::updateOrCreate(
            ['correo' => 'tecnico@cablevision.pe'],
            [
                'nombre' => 'Tecnico Campo 1',
                'password' => $claveInicial,
                'rol' => 'tecnico',
                'activo' => true,
            ]
        );
    }
}

