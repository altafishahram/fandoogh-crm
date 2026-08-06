<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

final class StaleRecordException extends DomainConflictException {}
