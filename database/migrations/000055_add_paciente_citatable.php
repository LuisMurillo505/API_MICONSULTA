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
        Schema::table('cita',function(Blueprint $table){
            $table->foreignId('paciente_id')->nullable()->change();
           $table->string('paciente_nombre')->after('paciente_id')->nullable();
           $table->string('telefono_paciente',15)->after('paciente_nombre')->nullable();

        });

        DB::table('tipocita')->insert(
            ['nombre'=>'Cita Agendada Por Paciente','created_at'=>now(),'updated_at'=>now()]
        );

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cita', function (Blueprint $table) {   
            $table->dropColumn('nombre_paciente');    
            $table->dropColumn('telefono_paciente');    
        });
    }
};
