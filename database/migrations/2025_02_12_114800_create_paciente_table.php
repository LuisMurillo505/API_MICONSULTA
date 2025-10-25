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
        Schema::create('PACIENTE', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('estado_id'); // campo 'estado_id' BIGINT UNSIGNED
            $table->string('nombre', 50); // campo 'nombre' VARCHAR(50)
            $table->string('apellido_paterno', 50); // campo 'apellido_paterno' VARCHAR(50)
            $table->string('apellido_materno', 50); // campo 'apellido_materno' VARCHAR(50)
            $table->date('fecha_nacimiento'); // campo 'fecha_nacimiento' DATE
            $table->text('foto'); // campo 'foto' TEXT
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente


            // Definir la clave foránea
            $table->foreign('estado_id')->references('id')->on('estado_paciente')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PACIENTE');
    }
};
