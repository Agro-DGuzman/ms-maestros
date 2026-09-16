@extends('backoffice::layout')

@section('titulo', 'Entrar')

@section('contenido')
    <form method="POST" action="{{ route('admin.verificar') }}" class="ingreso">
        @csrf
        <h1>Entrar al back-office</h1>

        <label for="correo">Correo</label>
        <input id="correo" type="email" name="correo" required autofocus autocomplete="username">

        <label for="contrasena">Contraseña</label>
        <input id="contrasena" type="password" name="contrasena" required autocomplete="current-password">

        <button type="submit">Entrar</button>
    </form>

    <style>
        .ingreso { max-width:360px; margin:40px auto; background:#fff; border:1px solid var(--borde);
                   padding:24px; display:flex; flex-direction:column; gap:6px; }
        .ingreso h1 { font-size:18px; margin:0 0 12px; }
        .ingreso label { font-size:13px; font-weight:600; margin-top:8px; }
        .ingreso input { font:inherit; padding:8px 10px; border:1px solid var(--borde); }
        .ingreso button { margin-top:18px; background:var(--verde); color:#fff; border:0; padding:10px; }
    </style>
@endsection
