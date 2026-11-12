<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instalaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('equipo_id')
                ->constrained('equipos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->date('fecha_instalacion');
            $table->date('fecha_retiro')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalaciones');
    }
};

