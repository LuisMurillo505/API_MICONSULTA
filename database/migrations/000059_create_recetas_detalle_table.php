<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recetas_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_id')->constrained('recetas')->onDelete('cascade');
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->onDelete('cascade');
            $table->string('medicamento_nombre')->nullable();
            $table->string('dosis');       // Ej: 500mg
            $table->string('frecuencia');    // Ej: Cada 8 horas
            $table->string('duracion');     // Ej: Por 7 días
            $table->integer('cantidad')->nullable();    // Cantidad de cajas/unidades
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recetas_detalle');
    }
};
