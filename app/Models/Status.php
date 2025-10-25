<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table='status';

    protected $primaryKey = 'id';

    protected $fillable=[
        'descripcion'
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function paciente(){
        return $this->hasMany(Pacientes::class,'status_id');
    } 
    public function usuario(){
        return $this->hasMany(Usuario::class,'status_id');
    }
    public function cita(){
        return $this->hasMany(Citas::class,'status_id');
    } 
    public function suscripcion(){
        return $this->hasMany(Suscripcion::class,'status_id');
    } 
    public function archivos_pacientes(){
        return $this->hasMany(ArchivosPaciente::class,'status_id');
    } 

    

}
