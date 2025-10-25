<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeTarifas extends Model
{
    protected $table='stripe_tarifas'; 

    protected $fillable = [
        'porcentaje',
        'fijo',
        'iva',
        'status_id',
    ];
    

    public function status(){
        return $this->BelongsTo(Status::class,'status_id');
    }
}
