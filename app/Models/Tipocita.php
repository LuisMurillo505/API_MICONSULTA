<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tipocita extends Model
{
    protected $table = 'tipocita';
    protected $fillable = [
        'nombre',
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function cita(){
        return $this->hasMany(Citas::class,'tipocita_id');
    }
}
