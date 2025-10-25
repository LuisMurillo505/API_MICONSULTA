<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgresoUsuarioGuia extends Model
{
    use HasFactory;

    protected $table = 'progreso_usuario_guia';

    protected $fillable = [
        'usuario_id',
        'clinica_id',
        'clave_paso',
        'esta_completado'
    ];

    protected $casts = [
        'esta_completado' => 'boolean'
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'usuario_id');
    }

    public function clinicas(){
        return $this->belongsTo(Clinicas::class, 'clinica_id');
    }

    public function pasoGuia()
    {
        return $this->belongsTo(PasoGuia::class, 'clave_paso', 'clave_paso');
    }
}
