<h1>¡Bienvenido(a) a nuestro sistema de citas!</h1>
<p>Hola {{ $usuario->clinicas->nombre  }}</p>

<p>Gracias por suscribirte a nuestro plan {{ $plan->nombre }}. Ahora puedes agendar tus citas de manera rápida y sencilla desde nuestra web.</p>

<p>A través de nuestro sistema con tu plan podrás:</p>
<ul>
  <li>Registrar tus pacientes y tus medicos</li>
  <li>Seleccionar el servicio que necesites</li>
  <li>Escoger la fecha y hora que más te convenga</li>
  <li>Recibir notificaciones con los detalles de tus citas</li>
  @if($plan->nombre === 'Estandar')
    <li>Registrar 5 usuarios medicos</li>
    <li>Recibir resumen de tus citas por Correo</li>
    <li>Subir hasta 5 achivos por paciente</li>
  @endif
  @if($plan->nombre === 'Pro')
    <li>Registrar 8 usuarios medicos</li>
    <li>Recibir resumen de tus citas por WhatsApp</li>
    <li>Subir hasta 10 achivos por paciente</li>
    <li>Conectar tus citas con Google Calendar</li>
  @endif
</ul>

<p>Estamos encantados de tenerte con nosotros y esperamos poder brindarte la mejor atención posible.</p>

<p>Si tienes alguna duda o necesitas ayuda, no dudes en contactarnos.</p>

<p>¡Comienza a agendar tu primera cita hoy mismo!</p>

<p>Saludos cordiales,</p>
<p><strong>El equipo de atención</strong></p>


