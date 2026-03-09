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
        Schema::create('kardexes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinica_id');
            $table->string('folio',50);
            $table->unsignedBigInteger('conceptomovimiento_id');
            $table->unsignedBigInteger('usuario_id');
            // $table->unsignedBigInteger('emisor_id')->nullable();
            // $table->unsignedBigInteger('receptor_id')->nullable();
            $table->unsignedBigInteger('almacen_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->date('fecha');
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('conceptomovimiento_id')->references('id')->on('concepto_movimientos')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('usuario')->onDelete('cascade');
            // $table->foreign('emisor_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('receptor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('almacen_id')->references('id')->on('almacenes')->onDelete('cascade');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade');
            $table->foreign('clinica_id')->references('id')->on('clinicas')->onDelete('cascade');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardexes');
    }
};
