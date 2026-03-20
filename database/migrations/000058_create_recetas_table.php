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
        Schema::create('recetas', function (Blueprint $table) {
           $table->id();
            $table->string('folio')->unique(); // Ej: RX-2026-0001
            $table->foreignId('paciente_id')->constrained('paciente')->onDelete('cascade'); // O tu tabla de pacientes
            $table->foreignId('personal_id')->constrained('personal')->onDelete('cascade');
            $table->foreignId('cita_id')->nullable()->constrained('cita')->onDelete('cascade');
            $table->text('diagnostico')->nullable(); // Diagnóstico médico
            $table->text('instrucciones')->nullable(); // Indicaciones generales
            $table->date('fecha')->nullable(); // Validez de la receta
            $table->date('expires_at')->nullable(); // Validez de la receta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
