@extends('backoffice::layout')
@section('titulo', 'Contactos')

@section('contenido')
    <form method="GET" action="{{ route('admin.contactos') }}" style="margin-bottom:16px;">
        <input type="search" name="q" value="{{ $criterio->texto }}"
               placeholder="Nombre, celular o socio" style="padding:8px; width:320px;">
        <select name="estado" style="padding:8px;">
            <option value="todas" @selected($criterio->estado->value === 'todas')>Todas</option>
            <option value="habilitadas" @selected($criterio->estado->value === 'habilitadas')>Habilitadas en SAP</option>
            <option value="no_habilitadas" @selected($criterio->estado->value === 'no_habilitadas')>No habilitadas en SAP</option>
        </select>
        <button type="submit">Buscar</button>
    </form>

    <p style="font-size:13px;" class="tenue">
        {{ $pagina->total }} persona(s) · página {{ $pagina->pagina }} de {{ $pagina->totalDePaginas }}
    </p>

    <table>
        <thead>
        <tr>
            <th>Persona</th><th>Celular</th><th>Socio</th><th>Grupo</th>
            {{-- Dos columnas y no una: lo que SAP marca y si la persona puede
                 entrar hoy son cosas distintas, y divergen. El boton actua
                 sobre la segunda, que es la que el operador controla. --}}
            <th>En SAP</th><th>Acceso a la App</th><th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($pagina->items as $fila)
            @php($contacto = $fila->replica)
            <tr>
                <td>
                    <strong>{{ $contacto->nombre }}</strong>
                    <span class="tenue">({{ $contacto->iniciales }})</span>
                    @if ($contacto->ausenteEnUltimaImportacion)
                        <div class="rojo" style="font-size:12px;">No vino en la última importación</div>
                    @endif
                </td>
                {{-- Completo, sin enmascarar (B12). --}}
                <td>{{ $contacto->celular ?? '—' }}</td>
                <td>{{ $contacto->razonSocial }}</td>
                <td>{{ $contacto->grupoEconomico ?? '—' }}</td>
                <td>
                    {{ $contacto->estaHabilitada ? 'Habilitada' : 'No habilitada' }}
                    @if ($fila->faltaDarleAcceso())
                        <div class="rojo" style="font-size:12px;">Falta darle acceso</div>
                    @elseif ($fila->conservaAccesoSinRespaldo())
                        <div class="rojo" style="font-size:12px;">Conserva acceso sin respaldo en SAP</div>
                    @endif
                </td>
                <td>
                    @if ($fila->tieneCredencial)
                        <strong>Sí</strong>
                    @else
                        <span class="tenue">No</span>
                    @endif
                </td>
                <td>
                    @if (! $contacto->celularEsValido)
                        <span class="rojo">Sin celular válido en SAP</span>
                    @elseif ($fila->tieneCredencial)
                        <form method="POST" action="{{ route('admin.deshabilitar', $contacto->idDePersona) }}">
                            @csrf
                            <input type="hidden" name="volver_a" value="{{ request()->fullUrl() }}">
                            <button type="submit">Quitar acceso</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.habilitar', $contacto->idDePersona) }}">
                            @csrf
                            <input type="hidden" name="volver_a" value="{{ request()->fullUrl() }}">
                            <button type="submit">Dar acceso</button>
                        </form>
                    @endif
                    <div style="margin-top:4px;">
                        <a href="{{ route('admin.historial', $contacto->idDePersona) }}">Historial</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay personas que coincidan con la búsqueda.</td></tr>
        @endforelse
        </tbody>
    </table>

    <nav style="margin-top:16px; display:flex; gap:10px;">
        @if ($pagina->pagina > 1)
            <a href="{{ request()->fullUrlWithQuery(['pagina' => $pagina->pagina - 1]) }}">« Anterior</a>
        @endif
        @if ($pagina->pagina < $pagina->totalDePaginas)
            <a href="{{ request()->fullUrlWithQuery(['pagina' => $pagina->pagina + 1]) }}">Siguiente »</a>
        @endif
    </nav>
@endsection
