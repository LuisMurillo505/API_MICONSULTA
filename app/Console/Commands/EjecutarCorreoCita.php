<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificacionService;

class EjecutarCorreoCita extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarea:ejecutar-correocita';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mandar Correo a usuarios Estandar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new NotificacionService();
        $controller->enviarCorreoMedico();

        $this->info('Controlador ejecutado correctamente');
    }
}
