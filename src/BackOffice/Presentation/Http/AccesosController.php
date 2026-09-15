<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Application\Accesos\ConcederAcceso\ConcederAcceso;
use BackOffice\Application\Accesos\ListarContactos\ListarContactos;
use BackOffice\Application\Accesos\ListarContactos\PaginaDeAccesos;
use BackOffice\Application\Accesos\RevocarAcceso\RevocarAcceso;
use BackOffice\Application\Bitacora\HistorialDePersona\HistorialDePersona;
use BackOffice\Domain\Operadores\Operador;
use Core\Contracts\Mediator;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Contactos\FiltroDeEstado;
use RuntimeException;

final readonly class AccesosController
{
    public function __construct(private Mediator $mediator) {}

    public function index(Request $pedido): View
    {
        $texto = $pedido->query('q');
        $estado = $pedido->query('estado', 'todas');

        $criterio = CriterioDeBusqueda::de(
            texto: is_string($texto) ? $texto : null,
            estado: FiltroDeEstado::tryFrom(is_string($estado) ? $estado : '') ?? FiltroDeEstado::Todas,
            // La barra de direcciones es entrada del usuario: `?pagina=-3`
            // haría estallar el objeto de valor, que sigue validando para
            // quien lo construya desde código.
            pagina: max(1, (int) $pedido->query('pagina', 1)),
            tamanoDePagina: Config::integer('backoffice.tamano_de_pagina'),
        );

        $resultado = $this->mediator->send(new ListarContactos($criterio));
        assert($resultado instanceof ResultWithValue);

        $pagina = $resultado->value();
        assert($pagina instanceof PaginaDeAccesos);

        return view('backoffice::contactos', [
            'pagina' => $pagina,
            'criterio' => $criterio,
        ]);
    }

    public function habilitar(Request $pedido, string $id): RedirectResponse
    {
        return $this->volver(
            $pedido,
            $this->mediator->send(new ConcederAcceso($id, $this->operador(), (string) $pedido->ip())),
            'Acceso concedido.',
        );
    }

    public function deshabilitar(Request $pedido, string $id): RedirectResponse
    {
        return $this->volver(
            $pedido,
            $this->mediator->send(new RevocarAcceso($id, $this->operador(), (string) $pedido->ip())),
            'Acceso quitado.',
        );
    }

    public function historial(string $id): View
    {
        $resultado = $this->mediator->send(new HistorialDePersona($id));
        assert($resultado instanceof ResultWithValue);

        return view('backoffice::historial', [
            'asientos' => $resultado->value(),
            'idDePersona' => $id,
        ]);
    }

    private function operador(): Operador
    {
        $operador = SesionDeOperador::actual();

        if ($operador === null) {
            throw new RuntimeException('La ruta no pasó por el middleware backoffice.sesion');
        }

        return $operador;
    }

    private function volver(Request $pedido, Result $resultado, string $exito): RedirectResponse
    {
        $destino = redirect()->to($this->destinoSeguro($pedido));

        return $resultado->isSuccess
            ? $destino->with('aviso', $exito)
            : $destino->with('error', $resultado->error->description);
    }

    /**
     * `volver_a` conserva la búsqueda y la página: quien habilita a alguien en
     * la página 3 vuelve a la página 3. Pero viene del formulario, así que
     * solo se acepta una ruta relativa de este mismo back-office; un destino
     * absoluto convertiría el botón en un salto a donde quiera el que arme el
     * formulario.
     */
    private function destinoSeguro(Request $pedido): string
    {
        $volverA = $pedido->input('volver_a');

        if (! is_string($volverA)) {
            return route('admin.contactos');
        }

        $ruta = parse_url($volverA, PHP_URL_PATH);
        $consulta = parse_url($volverA, PHP_URL_QUERY);

        if (! is_string($ruta) || ! str_starts_with($ruta, '/admin/')) {
            return route('admin.contactos');
        }

        return url($ruta.(is_string($consulta) && $consulta !== '' ? '?'.$consulta : ''));
    }
}
