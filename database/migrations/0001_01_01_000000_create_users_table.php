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
         Schema::create('USUARIO', function (Blueprint $table) {
            $table->id(); // Crea un campo id auto-incremental (bigint)
            $table->string('nombre_usuario', 50); // Crea un campo para nombre de usuario con tamaño de 50 caracteres
            $table->string('password'); // Crea un campo para la contraseña
            $table->string('correo', 50); // campo 'correo' VARCHAR(50)
            $table->timestamps(); // Crea los campos created_at y updated_at con el tipo timestamp
        });

        $hashedPassword = Hash::make('super5005');
    
        DB::table('USUARIO')->insert([
            ['nombre_usuario' => 'super' , 'correo'=>'luis10@gmail.com', 'password' => $hashedPassword, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('USUARIO');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
