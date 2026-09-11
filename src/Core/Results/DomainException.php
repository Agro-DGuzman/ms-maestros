<?php

declare(strict_types=1);

namespace Core\Results;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(private readonly Error $error)
    {
        parent::__construct($error->description);
    }

    public function getError(): Error
    {
        return $this->error;
    }
}
