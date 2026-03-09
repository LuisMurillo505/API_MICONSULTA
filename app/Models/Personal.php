<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
     protected $table = 'personal';
    
     protected $fillable = [
        'usuario_id',
        'booking_slug',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'especialidad_id',
        'cedula_profesional',
        'telefono',
        'foto',
        'puesto_id'
    ];
    protected $dates = ['created_at', 'updated_at', 'fecha_nacimiento'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

     public function disponibilidad()
    {
        return $this->hasMany(Disponibilidad::class, 'personal_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'usuario_id');
    }
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }
    public function puesto()
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    
    protected $appends = ['booking_url']; 

    // 2. El método se define en camelCases
    protected function getBookingUrlAttribute(): String
    {
        $clinicaSlug = $this->usuario?->clinicas?->booking_slug ?? 'general';
        $personalSlug = $this->booking_slug;
        return  env('FRONTEND_URL') . "/calendariopaciente/".  $clinicaSlug . "/". $personalSlug;
    }

    
}
