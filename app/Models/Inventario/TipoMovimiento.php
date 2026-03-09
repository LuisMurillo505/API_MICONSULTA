<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class TipoMovimiento extends Model
{

    public const ENTRADA = 1;
    public const SALIDA = 2;
    protected $table='tipo_movimientos';
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable=[
        'nombre'
    ];

    public function kardex(){
        $this->hasMany(Kardex::class,'tipomovimento_id');
    }
}
