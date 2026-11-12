<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pago_id')
                ->constrained('pagos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('tipo', 30)->default('vencimiento');
            $table->string('mensaje', 200);
            $table->enum('estado', ['pendiente', 'vista'])
                ->default('pendiente')
                ->index();
            $table->timestamp('fecha_generacion')->useCurrent();

            $table->unique(['pago_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};

