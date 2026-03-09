<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('concepto_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipomovimiento_id');
            $table->string('nombre',50);
            $table->timestamps();

            $table->foreign('tipomovimiento_id')->references('id')->on('tipo_movimientos')->onDelete('cascade');
        });

        DB::table('concepto_movimientos')->insert([
            ['tipomovimiento_id'=>1,'nombre'=>'Compra', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>1,'nombre'=>'Devolucion', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>1,'nombre'=>'Inventario Fisico', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>2,'nombre'=>'Asignacion', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>2,'nombre'=>'Venta', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>2,'nombre'=>'Devolucion', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>2,'nombre'=>'Traspaso', 'created_at' => now(), 'updated_at' => now()],
            ['tipomovimiento_id'=>2,'nombre'=>'Inventario Fisico', 'created_at' => now(), 'updated_at' => now()],

        ]);     
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concepto_movimientos');
    }
};
