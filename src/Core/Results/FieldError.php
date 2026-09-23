<?php

declare(strict_types=1);

namespace Core\Results;

/**
 * Un error de validación atado a un campo de la petición. Es lo que el cliente
 * puede resaltar al lado del campo; el resto de los errores no tiene dónde.
 */
final class FieldError extends Error
{
    public function __construct(
        public readonly string $field,
        string $code,
        string $structuredMessage,
    ) {
        parent::__construct($code, $structuredMessage, ErrorType::Validation);
    }
}
