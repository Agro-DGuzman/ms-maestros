<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Results\Result;

interface Mediator
{
    public function send(Request $peticion): Result;
}
