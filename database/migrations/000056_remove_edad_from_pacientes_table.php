<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paciente', function (Blueprint $table) {
            // Eliminamos la columna porque ya usamos el Accessor
            $table->dropColumn('edad');
        });
    }

    public function down(): void
    {
        Schema::table('paciente', function (Blueprint $table) {
            // En caso de rollback, volvemos a crear la columna
            $table->integer('edad')->nullable();
        });
    }
};