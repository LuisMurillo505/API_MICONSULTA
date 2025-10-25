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
        Schema::create('ESPECIALIDAD', function (Blueprint $table) {
            $table->id(); // equivalente a 'id BIGINT AUTO_INCREMENT'
            $table->string('descripcion', 50); // equivalente a 'descripcion VARCHAR(50)'
            $table->timestamps(); // crea 'created_at' y 'updated_at' automáticamente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ESPECIALIDAD');
    }
};
