<?php

declare(strict_types=1);

namespace Core\Results;

enum ErrorType: string
{
    case Failure = 'FAILURE';
    case Validation = 'VALIDATION';
    case Problem = 'PROBLEM';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
}
