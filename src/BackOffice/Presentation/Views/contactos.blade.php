@extends('backoffice::layout')
@section('titulo', 'Contactos')

@section('contenido')
    <form method="GET" action="{{ route('admin.contactos') }}" style="margin-bottom:16px;">
        <input type="search" name="q" value="{{ $criterio->texto }}"
               placeholder="Nombre, celular o socio" style="padding:8px; width:320px;">
        <select name="estado" style="padding:8px;">
            <option value="todas" @selected($criterio->estado->value === 'todas')>Todas</option>
            <option value="habilitadas" @selected($criterio->estado->value === 'habilitadas')>Habilitadas</option>
            <option value="no_habilitadas" @selected($criterio->estado->value === 'no_habilitadas')>No habilitadas</option>
        </select>
        <button type="submit">Buscar</button>
    </form>

    <p style="font-size:13px;" class="tenue">
        {{ $pagina->total }} persona(s) · página {{ $pagina->pagina }} de {{ $pagina->totalDePaginas() }}
    </p>

    <table>
        <thead>
        <tr>
            <th>Persona</th><th>Celular</th><th>Socio</th><th>Grupo</th><th>Acceso</th><th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($pagina->items as $contacto)
            <tr>
                <td>
                    <strong>{{ $contacto->nombre }}</strong>
                    <span class="tenue">({{ $contacto->iniciales }})</span>
                </td>
                {{-- El celular va completo, sin enmascarar (B12): el operador
                     necesita poder compararlo con lo que le dicen por telefono. --}}
                <td>{{ $contacto->celular ?? '—' }}</td>
                <td>{{ $contacto->razonSocial }}</td>
                <td>{{ $contacto->grupoEconomico ?? '—' }}</td>
                <td>
                    @if (! $contacto->celularEsValido)
                        <span class="rojo">Sin celular válido en SAP</span>
                    @elseif ($contacto->estaHabilitada)
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
                </td>
                <td><a href="{{ route('admin.historial', $contacto->idDePersona) }}">Historial</a></td>
            </tr>
        @empty
            <tr><td colspan="6">No hay personas que coincidan con la búsqueda.</td></tr>
        @endforelse
        </tbody>
    </table>

    <nav style="margin-top:16px; display:flex; gap:10px;">
        @if ($pagina->pagina > 1)
            <a href="{{ request()->fullUrlWithQuery(['pagina' => $pagina->pagina - 1]) }}">« Anterior</a>
        @endif
        @if ($pagina->pagina < $pagina->totalDePaginas())
            <a href="{{ request()->fullUrlWithQuery(['pagina' => $pagina->pagina + 1]) }}">Siguiente »</a>
        @endif
    </nav>
@endsection
