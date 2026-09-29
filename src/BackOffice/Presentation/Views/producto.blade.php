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

    {{-- Sigue a lo que se teclea, para ver si una URL carga antes de guardarla:
         guardar ya es publicarla. Si no carga acá, tampoco va a cargar en la App.
         Sin Referer, como la pide la App: un sitio que filtra por origen no
         puede mostrarla acá y negársela a ella. --}}
    <p>
        <img id="vista-previa" alt="{{ $producto->nombre }}" referrerpolicy="no-referrer"
             @if ($producto->enlaces->imagen !== null) src="{{ $producto->enlaces->imagen }}" @else hidden @endif
             style="max-width:240px; max-height:240px; border:1px solid var(--borde);">
        <span id="vista-previa-estado" style="font-size:13px;" hidden></span>
    </p>

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
                       value="{{ old($campo->value, $actual) }}" @if ($campo->esImagen()) data-vista-previa @endif
                       placeholder="https://…" style="padding:8px; width:100%; max-width:720px;">
                @error($campo->value)
                    <br><span class="rojo" style="font-size:13px;">{{ $message }}</span>
                @enderror
            </p>
        @endforeach
        <p class="tenue" style="font-size:13px;">Dejar un campo vacío quita ese enlace de la App.</p>
        <button type="submit">Guardar</button>
    </form>

    <script>
        (() => {
            const campo = document.querySelector('[data-vista-previa]');
            const imagen = document.getElementById('vista-previa');
            const estado = document.getElementById('vista-previa-estado');

            const avisar = (texto, clase = 'tenue') => {
                estado.textContent = texto;
                estado.className = clase;
                estado.hidden = texto === '';
            };
            const cargo = () => { imagen.hidden = false; avisar(''); };
            const fallo = () => { imagen.hidden = true; avisar('La imagen no carga desde esta dirección.', 'rojo'); };

            const mostrar = () => {
                const url = campo.value.trim();

                // Solo https, que es lo único que Enlace acepta: cualquier otra
                // cosa no se intenta cargar.
                if (! /^https:\/\//i.test(url)) {
                    imagen.hidden = true;
                    imagen.removeAttribute('src');
                    avisar('');
                    return;
                }

                if (imagen.getAttribute('src') === url) return;

                // Mientras la nueva carga, el navegador sigue mostrando la
                // anterior: una URL rota se vería bien durante los segundos que
                // tarda en fallar, y guardarla en ese rato sería publicarla.
                imagen.hidden = true;
                avisar('Cargando la imagen…');
                imagen.src = url;
            };

            imagen.addEventListener('load', () => { if (imagen.getAttribute('src')) cargo(); });
            imagen.addEventListener('error', () => { if (imagen.getAttribute('src')) fallo(); });

            // La guardada pudo terminar de cargar, o de fallar, antes de que
            // este script escuchara: se mira cómo quedó.
            if (imagen.getAttribute('src')) {
                if (! imagen.complete) { imagen.hidden = true; avisar('Cargando la imagen…'); }
                else if (imagen.naturalWidth > 0) cargo();
                else fallo();
            }

            campo.addEventListener('input', mostrar);
            mostrar();
        })();
    </script>

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
