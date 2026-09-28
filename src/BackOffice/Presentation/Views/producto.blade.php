@extends('backoffice::layout')
@section('titulo', $producto->nombre)

@section('contenido')
    <p><a href="{{ route('admin.productos') }}">« Volver a productos</a></p>
    <h2 style="font-size:18px;">{{ $producto->nombre }}</h2>

    {{-- Lo que viene de SAP o de script se muestra para reconocer el producto,
         pero no se edita acá. --}}
    <p class="tenue" style="font-size:14px;">
        Código: {{ $producto->itemCode ?? '—' }} · Categoría: {{ $producto->categoria ?? '—' }} ·
        @if ($producto->visibleEnApp())
            Se ve en la App
        @else
            <span class="rojo">No se ve en la App: {{ $producto->porQueNoSeVe() }}</span>
        @endif
    </p>

    @if ($producto->enlaces->imagen !== null)
        {{-- Si no carga acá, tampoco va a cargar en la App. --}}
        <p><img src="{{ $producto->enlaces->imagen }}" alt="{{ $producto->nombre }}"
                style="max-width:240px; max-height:240px; border:1px solid var(--borde);"></p>
    @endif

    <form method="POST" action="{{ route('admin.producto.guardar', $producto->id) }}" style="margin:16px 0;">
        @csrf
        @foreach ($campos as $campo)
            @php($actual = $producto->enlaces->valor($campo))
            <p>
                <label for="{{ $campo->value }}"><strong>{{ $campo->etiqueta() }}</strong></label>
                <span class="tenue" style="font-size:13px;">
                    ({{ $campo->esImagen() ? '.jpg, .jpeg, .png o .webp' : '.pdf' }})
                    @if ($actual !== null)
                        · <a href="{{ $actual }}" target="_blank" rel="noopener noreferrer">abrir la actual</a>
                    @endif
                </span><br>
                <input type="url" id="{{ $campo->value }}" name="{{ $campo->value }}"
                       value="{{ old($campo->value, $actual) }}"
                       placeholder="https://…" style="padding:8px; width:100%; max-width:720px;">
                @error($campo->value)
                    <br><span class="rojo" style="font-size:13px;">{{ $message }}</span>
                @enderror
            </p>
        @endforeach
        <p class="tenue" style="font-size:13px;">Dejar un campo vacío quita ese enlace de la App.</p>
        <button type="submit">Guardar</button>
    </form>

    <h3 style="font-size:16px;">Historial de cambios</h3>
    <table>
        <thead>
        <tr><th>Cuándo</th><th>Campo</th><th>Antes</th><th>Después</th><th>Quién</th><th>Desde</th></tr>
        </thead>
        <tbody>
        @forelse ($historial as $cambio)
            <tr>
                <td>{{ $cambio->ocurrioEl->format('d/m/Y H:i') }} UTC</td>
                <td>{{ \Maestros\Application\Productos\CampoDeEnlace::tryFrom($cambio->campo)?->etiqueta() ?? $cambio->campo }}</td>
                <td style="word-break:break-all;">{{ $cambio->anterior ?? '—' }}</td>
                <td style="word-break:break-all;">{{ $cambio->nuevo ?? '(quitado)' }}</td>
                <td>{{ $cambio->operador }}</td>
                <td>{{ $cambio->direccionIp }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Todavía no hay cambios para este producto.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
