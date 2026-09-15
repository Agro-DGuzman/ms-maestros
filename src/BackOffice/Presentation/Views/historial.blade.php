@extends('backoffice::layout')
@section('titulo', 'Historial')

@section('contenido')
    <p><a href="{{ route('admin.contactos') }}">« Volver a contactos</a></p>
    <h2 style="font-size:18px;">Historial de acceso · {{ $idDePersona }}</h2>

    <table>
        <thead>
        <tr><th>Cuándo</th><th>Qué pasó</th><th>Quién</th><th>Desde</th></tr>
        </thead>
        <tbody>
        @forelse ($asientos as $asiento)
            <tr>
                <td>{{ $asiento->ocurrioEl->format('d/m/Y H:i') }} UTC</td>
                <td>{{ $asiento->accion->comoTexto() }}</td>
                <td>{{ $asiento->operador }}</td>
                <td>{{ $asiento->direccionIp }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Todavía no hay movimientos para esta persona.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{-- Volver a habilitar a alguien que ya lo esta rota su credencial y mata
         sus sesiones abiertas. La confirmacion va en el onsubmit y tambien en
         el rotulo: un navegador sin JavaScript se saltea el confirm, no el
         texto del boton. --}}
    <form method="POST" action="{{ route('admin.habilitar', $idDePersona) }}"
          style="margin-top:20px;"
          onsubmit="return confirm('Volver a habilitar genera una credencial nueva y cierra cualquier sesión abierta de esta persona. ¿Seguís?');">
        @csrf
        <input type="hidden" name="volver_a" value="{{ route('admin.historial', $idDePersona) }}">
        <button type="submit">Volver a habilitar (rota la credencial)</button>
    </form>

    <p style="font-size:13px; margin-top:8px;" class="tenue">
        La credencial no se muestra nunca: el socio entra con su celular y el código que recibe por WhatsApp.
    </p>
@endsection
