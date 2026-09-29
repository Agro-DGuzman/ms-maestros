@extends('backoffice::layout')
@section('titulo', 'Productos')

@section('contenido')
    <form method="GET" action="{{ route('admin.productos') }}" style="margin-bottom:16px;">
        <input type="search" name="q" value="{{ $texto }}"
               placeholder="Nombre o código" style="padding:8px; width:320px;">
        <select name="filtro" style="padding:8px;">
            <option value="todos" @selected($filtro->value === 'todos')>Todos</option>
            <option value="sin-imagen" @selected($filtro->value === 'sin-imagen')>Sin imagen</option>
            <option value="sin-documentos" @selected($filtro->value === 'sin-documentos')>Sin documentos</option>
        </select>
        <button type="submit">Buscar</button>
    </form>

    <p style="font-size:13px;" class="tenue">{{ count($productos) }} producto(s)</p>

    <table>
        <thead>
        <tr>
            <th>Código</th><th>Producto</th><th>Categoría</th>
            <th>Se ve en la App</th><th>Imagen</th><th>Documentos</th><th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($productos as $producto)
            <tr>
                <td>{{ $producto->itemCode ?? '—' }}</td>
                <td><strong>{{ $producto->nombre }}</strong></td>
                <td>{{ $producto->categoria ?? '—' }}</td>
                <td>
                    @if ($producto->visibleEnApp())
                        Sí
                    @else
                        <span class="rojo" title="{{ $producto->porQueNoSeVe() }}">No</span>
                    @endif
                </td>
                <td>{{ $producto->enlaces->imagen === null ? '—' : 'Sí' }}</td>
                <td>{{ $producto->enlaces->documentosCargados() }} de 3</td>
                <td><a href="{{ route('admin.producto', $producto->id) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No hay productos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
