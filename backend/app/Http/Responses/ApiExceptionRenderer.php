<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Property\Exceptions\FileTooLargeException;
use App\Domain\Property\Exceptions\UnsupportedImageTypeException;
use App\Domain\SavedFilter\Exceptions\InvalidFilterException;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Shared\Exceptions\StaleRecordException;
use App\Domain\Tenancy\Exceptions\AgencyInactiveException;
use App\Domain\Tenancy\Exceptions\InactiveUserException;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\Exceptions\TenantMismatchException;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Domain\User\Exceptions\PasswordChangeRequiredException;
use App\Http\Middleware\AssignRequestId;
use App\Support\Http\ApiErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $requestId = $this->requestId($request);

        [$status, $code, $message, $details] = match (true) {
            $exception instanceof ValidationException => [
                422,
                ApiErrorCode::ValidationFailed,
                'اطلاعات واردشده معتبر نیست.',
                $exception->errors(),
            ],
            $exception instanceof AuthenticationException => [
                401,
                ApiErrorCode::Unauthenticated,
                'برای ادامه باید وارد حساب کاربری شوید.',
                null,
            ],
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => [
                403,
                ApiErrorCode::Forbidden,
                'اجازه انجام این عملیات را ندارید.',
                null,
            ],
            $exception instanceof AgencyInactiveException => [
                403,
                ApiErrorCode::AgencyInactive,
                'دسترسی آژانس غیرفعال شده است.',
                null,
            ],
            $exception instanceof PasswordChangeRequiredException => [
                403,
                ApiErrorCode::PasswordChangeRequired,
                'پیش از ادامه باید رمز عبور خود را تغییر دهید.',
                null,
            ],
            $exception instanceof InactiveUserException,
            $exception instanceof MissingTenantContextException,
            $exception instanceof TenantMismatchException => [
                403,
                ApiErrorCode::Forbidden,
                'اجازه انجام این عملیات را ندارید.',
                null,
            ],
            $exception instanceof InvalidCredentialsException => [
                422,
                ApiErrorCode::ValidationFailed,
                'ایمیل یا رمز عبور درست نیست.',
                null,
            ],
            $exception instanceof StaleRecordException => [
                409,
                ApiErrorCode::StaleRecord,
                'این رکورد هم‌زمان تغییر کرده است؛ اطلاعات را تازه‌سازی و دوباره تلاش کنید.',
                $exception->details,
            ],
            $exception instanceof DomainConflictException => [
                409,
                ApiErrorCode::DomainConflict,
                'این عملیات با وضعیت فعلی اطلاعات سازگار نیست.',
                $exception->details,
            ],
            $exception instanceof FileTooLargeException => [
                413,
                ApiErrorCode::FileTooLarge,
                'حجم فایل بیشتر از حد مجاز است.',
                null,
            ],
            $exception instanceof UnsupportedImageTypeException => [
                415,
                ApiErrorCode::UnsupportedMediaType,
                'نوع فایل تصویری پشتیبانی نمی‌شود.',
                null,
            ],
            $exception instanceof InvalidFilterException => [
                422,
                ApiErrorCode::InvalidFilter,
                'فیلتر انتخاب‌شده معتبر نیست.',
                $exception->warnings === [] ? null : ['warnings' => $exception->warnings],
            ],
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => [
                404,
                ApiErrorCode::ResourceNotFound,
                'اطلاعات درخواستی پیدا نشد.',
                null,
            ],
            $exception instanceof ThrottleRequestsException => [
                429,
                ApiErrorCode::RateLimited,
                'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.',
                null,
            ],
            $exception instanceof BadRequestHttpException => [
                400,
                ApiErrorCode::BadRequest,
                'ساختار درخواست معتبر نیست.',
                null,
            ],
            default => [
                500,
                ApiErrorCode::InternalError,
                'خطای پیش‌بینی‌نشده‌ای رخ داد؛ لطفاً دوباره تلاش کنید.',
                null,
            ],
        };

        if ($status === 500) {
            Log::error('Unhandled API exception.', [
                'exception' => $exception,
                'request_id' => $requestId,
            ]);
        }

        $error = [
            'code' => $code->value,
            'message' => $message,
            'request_id' => $requestId,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()
            ->json(['error' => $error], $status)
            ->header(AssignRequestId::HEADER, $requestId);
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $requestId = (string) Str::ulid();
        $request->attributes->set(AssignRequestId::ATTRIBUTE, $requestId);

        return $requestId;
    }
}
