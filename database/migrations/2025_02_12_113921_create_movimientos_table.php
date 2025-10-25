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
        Schema::create('MOVIMIENTOS', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('usuario_id'); // campo 'usuario_id' INT (en Laravel se usa 'unsignedBigInteger' para claves foráneas)
            $table->string('descripcion', 100); // campo 'descripcion' VARCHAR(100)
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente

            // Definir la clave foránea
            $table->foreign('usuario_id')->references('id')->on('usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('MOVIMIENTOS');
    }
};
