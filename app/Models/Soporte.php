<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soporte extends Model
{
    protected $table='soporte';
    protected $fillable = ['clinica_id','nombre', 'asunto', 'prioridad', 'mensaje'];

    public function clinica(){
        return $this->belongsTo(clinicas::class,'clinica_id');
    }
}
