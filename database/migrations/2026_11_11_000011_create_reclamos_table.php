<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('contrato_id')
                ->nullable()
                ->constrained('contratos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('usuario_registro_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('tecnico_id')
                ->nullable()
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('motivo', 255);
            $table->enum('diagnostico', ['pendiente', 'falla_confirmada', 'sin_falla'])
                ->default('pendiente');
            $table->enum('estado', ['abierto', 'en_proceso', 'resuelto'])
                ->default('abierto')
                ->index();
            $table->date('fecha_registro');
            $table->date('fecha_atencion')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamos');
    }
};
