<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use RuntimeException;

final class PasswordChangeRequiredException extends RuntimeException {}
