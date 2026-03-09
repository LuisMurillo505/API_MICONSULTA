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
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinica_id');
            $table->unsignedBigInteger('status_id');
            $table->unsignedBigInteger('subcategoria_id');
            $table->unsignedBigInteger('marca_id');
            $table->string('clave',50);
            $table->string('nombre',50);
            $table->text('foto')->nullable();
            $table->decimal('costo',10,2);
            $table->decimal('precio',10,2);
            $table->decimal('precio_sugerido',10,2)->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->timestamps();

            $table->foreign('marca_id')->references('id')->on('marcas')->onDelete('cascade');
            $table->foreign('subcategoria_id')->references('id')->on('subcategorias')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status')->onDelete('cascade');
            $table->foreign('clinica_id')->references('id')->on('clinicas')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
