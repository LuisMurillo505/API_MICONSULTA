<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class UsuarioAdmin extends Authenticatable
{
    protected $table = 'admin_users';
    protected $fillable = [
        'correo',
        'password',
        'status_id',
        'last_connection'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }
}
