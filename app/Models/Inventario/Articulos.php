<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Status;
use Illuminate\Database\Eloquent\Builder;

class Articulos extends Model
{
    protected $table='articulos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable=[
        'clinica_id',
        'status_id',
        'subcategoria_id',
        'marca_id',
        'clave',
        'foto',
        'nombre',
        'costo',
        'precio',
        'precio_sugerido',
        'fecha_caducidad'
    ];

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }

    public function marca(){
        return $this->belongsTo(Marcas::class,'marca_id');
    }
    public function subcategoria(){
       return $this->belongsTo(Subcategorias::class,'subcategoria_id');
    }

    public function almacenArticulos(){
        return $this->hasMany(AlmacenArticulo::class,'articulo_id');
    }

    public function movimientoInventario(){
        return $this->hasMany(MovimientosInventario::class,'articulo_id');
    }

    // public function articuloUsuario(){
    //     return $this->hasMany(ArticuloUsuario::class,'articulo_id');
    // }

    public function detalleVenta(){
        return $this->hasMany(DetalleVenta::class,'articulo_id');
    }

     protected static function booted()
    {
        static::addGlobalScope('clinicas', function (Builder $builder) {
            // Asumiendo que guardas el clinica_id en el usuario autenticado
            $builder->where('clinica_id', auth()->user()->getAttribute('clinica_id'));
        });
    }
}
