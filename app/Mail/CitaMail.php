<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $citas;
    public $clinica;

    public function __construct($citas,$clinica)
    {
        $this->citas = $citas;
        $this->clinica = $clinica;
    }

    public function build()
    {
        return $this->subject('Resumen de tus citas de hoy')
                    ->view('emails.Cita');
    }
}

