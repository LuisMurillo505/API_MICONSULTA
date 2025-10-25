<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Direcciones extends Model
{
     // Nombre de la tabla
     protected $table = 'direcciones';

     protected $primaryKey = 'id';
 
     protected $fillable = [
        'calle',
        'localidad',
        'ciudad',
     ];
 

     protected $dates = ['created_at', 'updated_at'];

    public function direccionable()
    {
        return $this->morphTo();
    }
 
    // public function clinicas()
    // {
    //     return $this->hasOne(Clinicas::class,'direccion_id');
    // }

    //  public function familiar_paciente()
    // {
    //     return $this->hasOne(Familiar_paciente::class,'direccion_id');
    // }

    //   public function pacientes()
    // {
    //     return $this->hasOne(Pacientes::class,'direccion_id');
    // }


}
