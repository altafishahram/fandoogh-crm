<?php

declare(strict_types=1);

namespace App\Support\Http;

enum ApiErrorCode: string
{
    case BadRequest = 'BAD_REQUEST';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case AgencyInactive = 'AGENCY_INACTIVE';
    case PasswordChangeRequired = 'PASSWORD_CHANGE_REQUIRED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case DomainConflict = 'DOMAIN_CONFLICT';
    case StaleRecord = 'STALE_RECORD';
    case FileTooLarge = 'FILE_TOO_LARGE';
    case UnsupportedMediaType = 'UNSUPPORTED_MEDIA_TYPE';
    case ValidationFailed = 'VALIDATION_FAILED';
    case InvalidFilter = 'INVALID_FILTER';
    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';
}
