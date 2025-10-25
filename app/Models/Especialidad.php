<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    
    protected $table = 'especialidad';
    protected $fillable = [
        'clinica_id',
        'descripcion',
        'status_id'
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }

}
