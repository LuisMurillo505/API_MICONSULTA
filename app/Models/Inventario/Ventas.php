<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class Ventas extends Model
{
    protected $table='ventas';
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable=[
        'clinica_id',
        'usuario_id',
        'nombre_cliente',
        'fecha',
        'total_venta',
    ];

    public function usuario(){
        return $this->belongsTo(Usuario::class,'usuario_id');
    }

    public function detalleVenta(){
        return $this->hasMany(DetalleVenta::class,'venta_id');
    }

    public function kardex(){
        return $this->hasOne(Kardex::class,'venta_id');
    }
}
