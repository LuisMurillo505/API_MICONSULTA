<h1>RESUMEN DE TUS CITAS DE HOY</h1>
<p>Hola!</p>

<p>Te escribe el equipo de mi consulta, te compartimos un resumen de tus citas de hoy 
    en tu clinica {{ $clinica }}</p>

<ul>
  @foreach ($citas as $cita)
    -{{ $cita->servicio->descripcion }} con {{ $cita->paciente->nombre }} {{ $cita->paciente->apellido_paterno }} 
      a las  {{ \Carbon\Carbon::parse($cita->hora_inicio)->format('h:i A')   }}
      <br>
  @endforeach
</ul>

<p>Por favor, asegúrate de estar disponible en la fecha y hora indicada.</p>

<p>Gracias por tu atención.</p>

<p>Saludos cordiales</p>

