<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PlanService;

class EjecutarVerificarSuscripcion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarea:ejecutar-verificarSuscripcion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verficar la el vencimiento de la suscripcion gratuita';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new PlanService();
        $controller->verificarSuscripciones();

        $this->info('Controlador ejecutado correctamente');
    }
}
