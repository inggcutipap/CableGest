<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_contrato', 20)->nullable()->unique();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('catalogo_id')
                ->constrained('catalogo_servicios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('venta_detalle_id')
                ->nullable()
                ->unique()
                ->constrained('venta_detalle')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->date('fecha_inicio');
            $table->enum('estado', ['activo', 'suspendido', 'baja'])
                ->default('activo')
                ->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};

