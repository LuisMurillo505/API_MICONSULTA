<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuarios_Permisos extends Model
{

    protected $table = 'usuario_permisos';
    protected $fillable = [
        'usuario_id', 
        'permiso_id', 
        'estado',
    ];

      // Definir la relación con la tabla USUARIOS (usuario_id)
      public function usuario()
      {
          return $this->belongsTo(Usuario::class, 'usuario_id');
      }
  
      // Definir la relación con la tabla PERMISOS (permiso_id)
      public function permiso()
      {
          return $this->belongsTo(Permisos::class, 'permiso_id');
      }
  
      // Si se quieren manejar las fechas automáticamente
      protected $dates = ['created_at', 'updated_at'];
}
