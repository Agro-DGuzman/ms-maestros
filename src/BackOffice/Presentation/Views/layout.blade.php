{{-- noindex porque estas paginas no deberian llegar nunca a un buscador,
     aunque la restriccion por IP se caiga. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo') · Back-office Agropartners</title>
    <style>
        :root { --verde:#06603E; --borde:#E4EAE7; --tinta:#0B1F17; --rojo:#B03A2E; --tenue:#7A8781; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, sans-serif; color: var(--tinta); background:#F6F8F7; }
        header { background: var(--verde); color:#fff; padding:14px 22px; display:flex; align-items:center; gap:16px; }
        header .marca { font-weight:700; }
        header form { margin-left:auto; }
        main { max-width:1100px; margin:24px auto; padding:0 16px; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--borde); }
        th, td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--borde); font-size:14px; }
        th { background:#F0F4F2; font-weight:600; }
        .aviso { background:#FFF7DF; border:1px solid #E8D08A; padding:10px 14px; margin-bottom:16px; }
        .error { background:#FDEEEB; border:1px solid #E5B4AC; padding:10px 14px; margin-bottom:16px; }
        .tenue { color: var(--tenue); }
        .rojo { color: var(--rojo); }
        button { font:inherit; cursor:pointer; padding:6px 12px; }
        a { color: var(--verde); }
    </style>
</head>
<body>
<header>
    <span class="marca">Back-office · Accesos</span>
    @if ($operador = \BackOffice\Presentation\Http\SesionDeOperador::actual())
        <span>{{ $operador->nombre }}</span>
        <form method="POST" action="{{ route('admin.salir') }}">
            @csrf
            <button type="submit">Salir</button>
        </form>
    @endif
</header>
<main>
    @if (session('error'))  <p class="error">{{ session('error') }}</p>  @endif
    @if (session('aviso'))  <p class="aviso">{{ session('aviso') }}</p>  @endif
    @yield('contenido')
</main>
</body>
</html>
