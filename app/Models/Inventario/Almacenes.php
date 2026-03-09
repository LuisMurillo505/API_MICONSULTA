<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Almacenes extends Model
{
    protected $table='almacenes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable=[
        'clinica_id',
        'nombre'
    ];

    public function movimientoInventario(){
        return $this->hasMany(MovimientosInventario::class,'almacen_id');
    }

    public function almacenArticulo(){
        return $this->hasMany(AlmacenArticulo::class,'almacen_id');
    }

    protected static function booted()
    {
        static::addGlobalScope('clinicas', function (Builder $builder) {
            // Asumiendo que guardas el clinica_id en el usuario autenticado
            $builder->where('clinica_id', auth()->user()->getAttribute('clinica_id'));
        });
    }
}
