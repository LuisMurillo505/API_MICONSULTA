<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Subcategorias extends Model
{
    protected $table='subcategorias';
     /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable=[
        'categoria_id',
        'nombre',
    ];

    public function categoria(){
        return $this->belongsTo(Categorias::class);
    }

    public function articulos(){
        return $this->hasMany(Articulos::class,'subcategoria_id');
    }
}
