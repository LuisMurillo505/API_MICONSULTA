<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomVerifyEmail;

class Usuario extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

     protected $table = 'usuario';
    protected $fillable = [
        'clinica_id',
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

    public function personal()
    {
        return $this->hasOne(Personal::class);
    }

    public function clinicas()
    {
        return $this->belongsTo(Clinicas::class, 'clinica_id');
    }

    public function google(){
        return $this->hasOne(Google::class,'usuario_id');
    }

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }

    public function getEmailAttribute()
    {
        return $this->correo;
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['correo'] = $value;
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }
    

    
}
