<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Contrasena;

use Illuminate\Console\Command;

/**
 * Arma una entrada de `BACKOFFICE_OPERADORES`.
 *
 * La contraseña se pregunta en vez de recibirse como argumento: un argumento
 * queda en el historial del shell y en la lista de procesos.
 */
final class HashDeContrasenaCommand extends Command
{
    private const MINIMO = 12;

    protected $signature = 'backoffice:hash {correo} {nombre}';

    protected $description = 'Genera la linea correo|hash|Nombre para BACKOFFICE_OPERADORES';

    public function handle(): int
    {
        $contrasena = $this->secret('Contraseña');

        // `secret()` devuelve null si no hay con quién dialogar, por ejemplo
        // corriendo el comando sin terminal.
        if (! is_string($contrasena) || mb_strlen($contrasena) < self::MINIMO) {
            $this->error('La contraseña necesita al menos '.self::MINIMO.' caracteres.');

            return self::FAILURE;
        }

        $this->line(OperadorConContrasena::registroPara(
            correo: (string) $this->argument('correo'),
            contrasena: $contrasena,
            nombre: (string) $this->argument('nombre'),
        ));
        $this->newLine();
        // El nombre lleva espacios: sin comillas, dotenv corta la linea con un
        // error de parseo y la aplicacion no arranca.
        $this->comment('Pegalo entre comillas en BACKOFFICE_OPERADORES; varias personas van separadas por «;».');

        return self::SUCCESS;
    }
}
