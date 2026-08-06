<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

class DomainConflictException extends DomainException
{
    /** @param array<string, mixed>|null $details */
    public function __construct(string $message, public readonly ?array $details = null)
    {
        parent::__construct($message);
    }
}
