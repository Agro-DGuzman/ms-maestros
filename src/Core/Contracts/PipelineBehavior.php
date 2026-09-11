<?php

declare(strict_types=1);

namespace Core\Contracts;

use Closure;
use Core\Results\Result;

interface PipelineBehavior
{
    /** @param Closure(Request): Result $siguiente */
    public function handle(Request $peticion, Closure $siguiente): Result;
}
