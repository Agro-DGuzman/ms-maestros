<?php

declare(strict_types=1);

namespace Core\Results;

class Error
{
    public readonly string $description;

    private static ?self $none = null;

    /** @param list<string|int|float> $args */
    public function __construct(
        public readonly string $code,
        public readonly string $structuredMessage,
        public readonly ErrorType $type,
        array $args = [],
    ) {
        $this->description = self::render($structuredMessage, $args);
    }

    /** @param list<string|int|float> $args */
    private static function render(string $template, array $args): string
    {
        if ($args === []) {
            return $template;
        }

        $indice = 0;

        return preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $coincidencia) use ($args, &$indice): string {
                if (! array_key_exists($indice, $args)) {
                    return $coincidencia[0];
                }

                return (string) $args[$indice++];
            },
            $template,
        ) ?? $template;
    }

    /** El vacío con el que se compara por identidad. */
    public static function none(): self
    {
        return self::$none ??= new self('', '', ErrorType::Failure);
    }

    public static function nullValue(): self
    {
        return new self('General.Null', 'Se recibió un valor nulo', ErrorType::Failure);
    }

    public static function failure(string $code, string $structuredMessage, string|int|float ...$args): self
    {
        return new self($code, $structuredMessage, ErrorType::Failure, array_values($args));
    }

    public static function validation(string $code, string $structuredMessage, string|int|float ...$args): self
    {
        return new self($code, $structuredMessage, ErrorType::Validation, array_values($args));
    }

    public static function notFound(string $code, string $structuredMessage, string|int|float ...$args): self
    {
        return new self($code, $structuredMessage, ErrorType::NotFound, array_values($args));
    }

    public static function problem(string $code, string $structuredMessage, string|int|float ...$args): self
    {
        return new self($code, $structuredMessage, ErrorType::Problem, array_values($args));
    }

    public static function conflict(string $code, string $structuredMessage, string|int|float ...$args): self
    {
        return new self($code, $structuredMessage, ErrorType::Conflict, array_values($args));
    }
}
