<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pacientes extends Model
{
    protected $table = 'paciente';

    // Si deseas especificar la clave primaria (esto es opcional si es 'id')
    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'clinica_id',
        'status_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'alias',
        'sexo',
        'curp',
        'nss',
        'paciente',
        'fecha_nacimiento',
        'edad',
        'foto',
        'telefono',
    ];

    protected $dates = ['created_at', 'updated_at', 'fecha_nacimiento'];

    //relacion con la trabla somatrometria
    public function somatometria(){
        return $this->hasOne(Somatometria_Paciente::class,'paciente_id');
    } 

    // Relación con la tabla 'estado' (estado_id)
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function clinicas()
    {
        return $this->belongsTo(Clinicas::class, 'clinica_id');
    }

    public function direccion()
    {
        return $this->morphOne(Direcciones::class, 'direccionable');
    }
    public function Observaciones(){
        return $this->hasMany(Observaciones::class,'paciente_id');
    }   
    public function Familiar_paciente(){
        return $this->hasMany(Familiar_paciente::class,'paciente_id');
    }

    public function historial_clinico(){
        return $this->hasOne(Historial_clinico::class,'paciente_id');
    }

}
