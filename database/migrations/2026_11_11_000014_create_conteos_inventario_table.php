<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteos_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('total_sistema');
            $table->unsignedInteger('total_fisico');
            $table->unsignedInteger('coincidentes');
            $table->unsignedInteger('verificados');
            $table->decimal('exactitud', 5, 2);
            $table->longText('discrepancias');
            $table->timestamp('fecha')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteos_inventario');
    }
};

