<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class AlmacenArticulo extends Model
{
    protected $table='almacen_articulos';
    protected $fillable=[
        'articulo_id',
        'almacen_id',
        'stock'
    ];

    public function articulos(){
        return $this->belongsTo(Articulos::class,'articulo_id');
    }

    public function almacenes(){
        return $this->belongsTo(Almacenes::class,'almacen_id');
    }
}
