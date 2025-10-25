<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasoGuia extends Model
{
    use HasFactory;

    protected $table = 'tutorial_pasos';

    protected $fillable = [
        'clave_paso',
        'titulo',
        'descripcion',
        'ruta_destino',
        'elemento_seleccionado',
        'icono',
        'color',
        'paso'
    ];

    public function progreso()
    {
        return $this->hasMany(ProgresoUsuarioGuia::class, 'clave_paso', 'clave_paso');
    } 
}
