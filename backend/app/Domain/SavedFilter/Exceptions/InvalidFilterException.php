<?php

declare(strict_types=1);

namespace App\Domain\SavedFilter\Exceptions;

use DomainException;

final class InvalidFilterException extends DomainException
{
    /** @param list<string> $warnings */
    public function __construct(string $message, public readonly array $warnings = [])
    {
        parent::__construct($message);
    }
}
