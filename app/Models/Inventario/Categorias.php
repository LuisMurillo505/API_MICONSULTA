<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Categorias extends Model
{
    protected $table='categorias';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable=[
        'clinica_id',
        'nombre'
    ];

    public function subcategorias(){
        return $this->hasMany(Subcategorias::class,'categoria_id');
    }

    protected static function booted()
    {
        static::addGlobalScope('clinicas', function (Builder $builder) {
            // Asumiendo que guardas el clinica_id en el usuario autenticado
            $builder->where('clinica_id', auth()->user()->getAttribute('clinica_id'));
        });
    }
}
