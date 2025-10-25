<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificaciones extends Model
{
    protected $table='notificaciones';

    protected $fillable=[
        'personal_id',
        'mensaje',
        'estado'
    ];
    protected $dates = ['created_at', 'updated_at'];

    // Relación con la tabla 'personal' (personal_id)
    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

}
