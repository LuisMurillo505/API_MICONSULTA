<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Modificar tabla Clinicas
        Schema::table('clinicas', function (Blueprint $table) {
            $table->string('booking_slug')->nullable()->unique()->after('nombre');
        });

        // Modificar tabla Personal (Doctores)
        Schema::table('personal', function (Blueprint $table) {
            $table->string('booking_slug')->nullable()->unique()->after('puesto_id');
        });

        // Poblar registros existentes con un slug profesional
        $this->populateExistingSlugs();
    }

    private function populateExistingSlugs() {
        // Para Clínicas: usamos el nombre de la clínica
        DB::table('clinicas')->get()->each(function ($item) {
            DB::table('clinicas')->where('id', $item->id)->update([
                'booking_slug' => Str::slug($item->nombre) . '-' . Str::random(5)
            ]);
        });

        // Para Personal: usamos su nombre profesional
        DB::table('personal')->get()->each(function ($item) {
            DB::table('personal')->where('id', $item->id)->update([
                'booking_slug' => Str::slug($item->nombre) . '-' . Str::random(5)
            ]);
        });
    }

    public function down(): void {
        Schema::table('clinicas', function (Blueprint $table) {
            $table->dropColumn('booking_slug');
        });
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn('booking_slug');
        });
    }
};