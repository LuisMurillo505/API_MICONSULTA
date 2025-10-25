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
        Schema::create('CITA', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('personal_id'); // campo 'personal_id' BIGINT UNSIGNED
            $table->unsignedBigInteger('paciente_id'); // campo 'paciente_id' BIGINT UNSIGNED
            $table->unsignedBigInteger('servicio_id'); 
            $table->date('fecha_cita'); // campo 'fecha_cita' DATE
            $table->time('hora'); // campo 'hora' TIME
            $table->string('estado', 15); // campo 'estado' VARCHAR(15)
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente

            // Definir las claves foráneas
            $table->foreign('personal_id')->references('id')->on('personal')->onDelete('cascade');
            $table->foreign('paciente_id')->references('id')->on('paciente')->onDelete('cascade');
            $table->foreign('servicio_id')->references('id')->on('servicio')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CITA');
    }
};
