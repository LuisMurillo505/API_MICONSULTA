<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    protected $table='servicio';

    protected $fillable=[
        'clinica_id',
        'descripcion',
        'duracion',
        'precio',
        'status_id'
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function clinica() {
        return $this->belongsTo(Clinicas::class,'clinica_id');
    }

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }
}
