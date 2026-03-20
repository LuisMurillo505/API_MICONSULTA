<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    protected $table = 'recetas';

    // Si deseas especificar la clave primaria (esto es opcional si es 'id')
    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'folio',
        'cita_id',
        'paciente_id',
        'personal_id',
        'fecha',
        'expires_at',
        'instrucciones',
        'diagnostico'
    ];

    public function paciente()
    {
        return $this->belongsTo(Pacientes::class,'paciente_id');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class,'personal_id');
    }

    public function recetaDetalle(){
        return $this->hasMany(RecetaDetalle::class,'receta_id');
    }

    

}
