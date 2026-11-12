<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->enum('accion', ['crear', 'actualizar', 'anular']);
            $table->string('tabla_afectada', 50);
            $table->unsignedBigInteger('registro_id');
            $table->longText('detalle');
            $table->timestamp('fecha')->useCurrent()->index();

            $table->index(['tabla_afectada', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};

