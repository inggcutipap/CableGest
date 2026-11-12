<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalogo_id')
                ->constrained('catalogo_servicios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('marca', 50);
            $table->string('serie', 80)->unique();
            $table->enum('estado', ['disponible', 'instalado', 'en_reparacion', 'baja'])
                ->default('disponible')
                ->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
