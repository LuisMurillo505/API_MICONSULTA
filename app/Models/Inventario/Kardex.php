<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class Kardex extends Model
{
    protected $table='kardexes';
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable=[
        'clinica_id',
        'conceptomovimiento_id',
        'folio',
        'usuario_id',
        'emisor_id',
        'receptor_id',
        'almacen_id',
        'venta_id',
        'fecha',
        'observaciones'
    ];

    public function conceptoMovimiento(){
        return $this->belongsTo(ConceptoMovimientos::class,'conceptomovimiento_id');
    }

    public function user(){
        return $this->belongsTo(Usuario::class,'usuario_id');
    }

    // public function emisor(){
    //     return $this->belongsTo(Usuario::class,'emisor_id');
    // }

    // public function receptor(){
    //     return $this->belongsTo(Usuario::class,'receptor_id');
    // }

    public function movimientoInventario(){
        return $this->hasMany(MovimientosInventario::class,'kardex_id');
    }
    public function almacen(){
        return $this->belongsTo(Almacenes::class,'almacen_id');
    }

    public function ventas(){
        return $this->belongsTo(Ventas::class,'venta_id');
    }
}
