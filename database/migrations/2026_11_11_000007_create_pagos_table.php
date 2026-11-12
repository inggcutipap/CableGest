<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')
                ->constrained('contratos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->char('periodo', 7)->nullable();
            $table->decimal('monto', 10, 2);
            $table->date('fecha_vencimiento')->index();
            $table->date('fecha_pago')->nullable()->index();
            $table->enum('estado', ['pendiente', 'pagado', 'vencido', 'anulado'])
                ->default('pendiente')
                ->index();
            $table->foreignId('usuario_cobro_id')
                ->nullable()
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('metodo_pago', 30)->nullable();
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();

            $table->unique(['contrato_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

