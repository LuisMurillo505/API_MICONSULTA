<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class MovimientosInventario extends Model
{
    protected $table='movimientos_inventario';
    
     /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable=[
        'kardex_id',
        'articulo_id',
        'cantidad',
    ];  

    public function kardex(){
        return $this->belongsTo(Kardex::class,'kardex_id');
    }
    public function articulos(){
        return $this->belongsTo(Articulos::class,'articulo_id');
    }

   
}
