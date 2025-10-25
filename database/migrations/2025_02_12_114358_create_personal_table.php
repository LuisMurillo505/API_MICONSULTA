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
        Schema::create('PERSONAL', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('usuario_id'); // campo 'usuario_id' BIGINT UNSIGNED
            $table->string('nombre', 50); // campo 'nombre' VARCHAR(50)
            $table->string('apellido_paterno', 50); // campo 'apellido_paterno' VARCHAR(50)
            $table->string('apellido_materno', 50); // campo 'apellido_materno' VARCHAR(50)
            $table->date('fecha_nacimiento'); // campo 'fecha_nacimiento' DATE
            $table->unsignedBigInteger('especialidad_id'); // campo 'especialidad_id' BIGINT UNSIGNED
            $table->string('cedula_profesional', 50); // campo 'cedula_profesional' VARCHAR(50)
            $table->integer('telefono'); // campo 'telefono' INT
            $table->text('foto'); // campo 'foto' TEXT
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente

            // Definir las claves foráneas
            $table->foreign('usuario_id')->references('id')->on('usuario')->onDelete('cascade');
            $table->foreign('especialidad_id')->references('id')->on('especialidad')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PERSONAL');
    }
};
