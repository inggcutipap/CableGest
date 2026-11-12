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
        Usuario::updateOrCreate(
            ['correo' => 'admin@cablevision.pe'],
            [
                'nombre' => 'Administrador CableGest',
                'password' => env('ADMIN_SEED_PASSWORD', 'CableGest2026!'),
                'rol' => 'administrador',
                'activo' => true,
            ]
        );
    }
}

