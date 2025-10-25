<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Historial extends Model
{
    protected $table='historial';

    protected $fillable=[
        'descripcion'
    ];

    public function usuario(){
        return $this->belongsTo(Usuario::class,'usuario_id');
    }

    protected $dates = ['created_at', 'updated_at'];

}
