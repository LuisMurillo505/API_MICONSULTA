<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistroMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;

    public $plan;

    public function __construct($usuario,$plan)
    {
        $this->usuario = $usuario;
        $this->plan = $plan;
    }

    public function build()
    {
        return $this->subject('Bienvenido a Mi Consulta')
                    ->view('emails.Registro');
    }
}

