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
        Schema::create('EXPEDIENTE', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('paciente_id'); // campo 'paciente_id' BIGINT UNSIGNED
            $table->unsignedBigInteger('personal_id'); // campo 'personal_id' BIGINT UNSIGNED
            $table->unsignedBigInteger('cita_id'); // campo 'cita_id' BIGINT UNSIGNED
            $table->text('observacion'); // campo 'observacion' TEXT
            $table->date('fecha'); // campo 'fecha' DATE
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente

            // Definir las claves foráneas
            $table->foreign('paciente_id')->references('id')->on('paciente')->onDelete('cascade');
            $table->foreign('personal_id')->references('id')->on('personal')->onDelete('cascade');
            $table->foreign('cita_id')->references('id')->on('cita')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('EXPEDIENTE');
    }
};
