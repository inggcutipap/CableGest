<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipo_id')
                ->constrained('equipos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->enum('tipo_movimiento', ['entrada', 'salida']);
            $table->string('motivo', 100)->nullable();
            $table->timestamp('fecha')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};

