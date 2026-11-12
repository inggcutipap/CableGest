<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_interno', 10)->unique();
            $table->string('nombre', 200);
            $table->enum('tipo', [
                'plan_internet',
                'plan_tv',
                'plan_combo',
                'cargo_unico',
                'equipo',
            ]);
            $table->enum('segmento', [
                'residencial',
                'comercial',
                'empresarial',
            ])->nullable();
            $table->unsignedInteger('velocidad_mbps')->nullable();
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_servicios');
    }
};

