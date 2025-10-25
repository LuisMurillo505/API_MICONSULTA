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
        Schema::create('USUARIO_PERMISOS', function (Blueprint $table) {
            $table->id(); // campo 'id' BIGINT AUTO_INCREMENT
            $table->unsignedBigInteger('usuario_id'); // campo 'usuario_id' INT (en Laravel se usa 'unsignedBigInteger' para claves foráneas)
            $table->unsignedBigInteger('permiso_id'); // campo 'permiso_id' INT (igual, usamos 'unsignedBigInteger')
            $table->integer('estado'); // campo 'estado' INT
            $table->timestamps(); // campos 'created_at' y 'updated_at' automáticamente

            // Definir las claves foráneas
            $table->foreign('usuario_id')->references('id')->on('usuario')->onDelete('cascade');
            $table->foreign('permiso_id')->references('id')->on('permisos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('USUARIO_PERMISOS');
    }
};
