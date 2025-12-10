<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $temporalpassword;

    public function __construct($usuario, $temporalpassword)
    {
        $this->user = $usuario;
        $this->temporalpassword = $temporalpassword;
    }

    public function build()
    {
        return $this->subject('Tu nueva contraseña temporal')
                    ->view('emails.temporary_password');
    }
}

