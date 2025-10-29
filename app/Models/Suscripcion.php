<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Suscripcion extends Model
{
    use HasFactory;
    protected $table = 'suscripcion';
    protected $fillable = [
        'plan_id',
        'clinica_id',
        'inicio_plan',
        'status_id',
        'stripe_subscription_id',
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function clinica()
    {
        return $this->belongsTo(Clinicas::class,'clinica_id');
    }
    public function plan() {
        return $this->belongsTo(Planes::class,'plan_id');
    }

    public function status(){
        return $this->belongsTo(Status::class,'status_id');
    }

    protected $appends = ['dias_restantes'];
    public function getDiasRestantesAttribute()
    {
      
        // Define hoy y el inicio del plan
            $hoy = Carbon::today();
            $inicio = Carbon::parse($this->inicio_plan ?? null); 

            // Calcula los días pasados desde el inicio del plan
            $diasPasados = $inicio->diffInDays($hoy);

            // Calcula los días restantes (o días negativos si el plan ha terminado)
            $diasRestantes = ($this->plan->duracion_dias ?? 0 )  - $diasPasados;

            return $diasRestantes;
    }
}



