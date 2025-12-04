<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma tu correo en Mi Consulta')
            ->greeting('¡Hola!')
            ->line('Gracias por registrarte en nuestro sistema. Para continuar, verifica tu correo electrónico.')
            ->action('Verificar correo electrónico', $url)
            ->line('Es importante que confirmes tu correo para seguir disfrutando de nuestros servicios');
    }
}

