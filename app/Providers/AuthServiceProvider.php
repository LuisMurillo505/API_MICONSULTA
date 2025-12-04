<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class AuthServiceProvider extends ServiceProvider
{
    // public function boot(): void
    // {
    //     // Personalizar URL del email de verificación
    //     VerifyEmail::createUrlUsing(function ($notifiable) {

    //         // URL firmada generada por la API
    //         $apiUrl = URL::temporarySignedRoute(
    //             'verification.verify',
    //             now()->addMinutes(60),
    //             [
    //                 'id' => $notifiable->getKey(),
    //                 'hash' => sha1($notifiable->email)
    //             ]
    //         );

    //         // URL del proyecto principal (frontend)
    //         $frontendUrl = config('app.frontend_url') . '/email/verificado';

    //         // Enviamos la URL firmada como parámetro al frontend
    //         return $frontendUrl . '?redirect=' . urlencode($apiUrl);
    //     });
    // }

    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {

            $signedUrl = URL::temporarySignedRoute(
                'verification.verify', // ruta de la API
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->email),
                ]
            );

            return config('app.frontend_url') . '/email/verificar?redirect=' . urlencode($signedUrl);
        });
    }

    // public function boot(): void
    // {
    //    VerifyEmail::createUrlUsing(function ($notifiable) {

    //         // URL firmada de la API (la que Laravel valida)
    //         $signedUrl = URL::temporarySignedRoute(
    //             'verification.verify',
    //             now()->addMinutes(60),
    //             [
    //                 'id' => $notifiable->getKey(),
    //                 'hash' => sha1($notifiable->email)
    //             ]
    //         );

    //         // URL del frontend donde manejarás tu lógica personalizada
    //         return config('app.frontend_url') . '/email/verify?url=' . urlencode($signedUrl);
    //     });
    // }

    
}

