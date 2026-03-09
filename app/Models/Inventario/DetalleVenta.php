<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table='detalle_ventas';

     /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable=[
        'venta_id',
        'articulo_id',
        'cantidad',
        'precio_unitario',
        'precio_sugerido',
        'subtotal'
    ];

    public function articulos(){
        return $this->belongsTo(Articulos::class,'articulo_id');
    }

    public function venta(){
        return $this->belongsTo(Articulos::class,'venta_id');
    }
}
