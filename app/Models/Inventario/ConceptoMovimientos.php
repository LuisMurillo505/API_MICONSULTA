<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class ConceptoMovimientos extends Model
{
    public const COMPRA_E = 1;
    public const DEVOLUCION_E = 2;
    public const INVENTARIOFISICO_E = 3;
    public const ASIGNACION_S = 4;
    public const VENTA_S = 5;
    public const DEVOLUCION_S = 6;
    public const TRASPASO_S = 7;
    public const INVENTARIOFISICO_S = 8;

    

    protected $table='concepto_movimientos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable=[
        'tipomovimiento_id',
        'nombre'
    ];

    public function tipomovimiento(){
        return $this->belongsTo(TipoMovimiento::class,'tipomovimiento_id');
    }
}
