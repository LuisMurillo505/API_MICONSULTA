<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuscripcionCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $clinica;

    public function __construct($clinica)
    {
        $this->clinica = $clinica;
    }

    public function build()
    {
        return $this->subject('Suscripcion Cancelada')
                    ->view('emails.SuscripcionCancelada');
    }
}

