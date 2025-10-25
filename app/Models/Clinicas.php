<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Clinicas extends Model
{
    protected $table = 'clinicas';
    protected $fillable = [
        'nombre',
        // 'direccion_id',
        'telefono',
        'RFC',
        'foto',
        'stripe_customer_id',
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function usuario()
    {
        return $this->hasOne(Usuario::class);
    }

     public function paciente()
    {
        return $this->hasOne(Pacientes::class);
    }

    public function suscripcion() {
        return $this->hasOne(Suscripcion::class,'clinica_id');
    }

    // public function direccion() {
    //     return $this->belongsTo(Direcciones::class,'direccion_id');
    // }


     public function servicio() {
        return $this->hasOne(Servicio::class,'clinica_id');
    }

}



