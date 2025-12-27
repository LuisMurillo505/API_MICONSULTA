<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppService;

class EjecutarControlador extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarea:ejecutar-controlador';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta el controlador a cierta hora';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new WhatsAppService();
        $controller->enviarWhatsAppMedico();

        $this->info('Controlador ejecutado correctamente');
    }
}
