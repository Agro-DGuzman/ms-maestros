<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Results\Result;

interface RequestHandler
{
    public function handle(Request $peticion): Result;
}
