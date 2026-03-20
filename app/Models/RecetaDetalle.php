<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecetaDetalle extends Model
{
    protected $table = 'recetas_detalle';

    // Si deseas especificar la clave primaria (esto es opcional si es 'id')
    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'receta_id',
        'articulo_id',
        'medicamento_nombre',
        'dosis',
        'frecuencia',
        'duracion',
        'cantidad'
    ];

    public function receta()
    {
        return $this->belongsTo(Receta::class,'receta_id');
    }
    
}
