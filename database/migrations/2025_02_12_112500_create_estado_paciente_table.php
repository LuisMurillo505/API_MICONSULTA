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
        Schema::create('ESTADO_PACIENTE', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->string('estado', 50); // campo 'descripcion' VARCHAR(50)
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ESTADO_PACIENTE');
    }
};
