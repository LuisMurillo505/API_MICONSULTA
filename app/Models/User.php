<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

     protected $table = 'usuario';

     protected $primaryKey='id';
    protected $fillable = [
        'correo',
        'password',
        'estado'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'correo',
        'clinica_id',
        'password',
        'status_id',
        'last_connection'    
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function personal(){
        return $this->hasOne(Personal::class,'usuario_id');
    }

    public function clinicas()
    {
        return $this->belongsTo(Clinicas::class, 'clinica_id');
    }

    public function google(){
        return $this->hasOne(Google::class,'usuario_id');
    }
}
