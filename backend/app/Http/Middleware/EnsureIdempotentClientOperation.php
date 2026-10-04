<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\ClientOperation;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotentClientOperation
{
    public function handle(Request $request, Closure $next, string $resourceType): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return $next($request);
        }
        if (! Str::isUuid($key)) {
            throw new DomainConflictException('کلید یکتای عملیات دستگاه معتبر نیست.');
        }

        /** @var User $user */
        $user = $request->user();
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode(
            $request->all(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        $operation = ClientOperation::query()
            ->where('user_id', $user->getKey())
            ->where('operation_key', $key)
            ->first();

        if ($operation instanceof ClientOperation) {
            return $this->replay($operation, $hash);
        }

        try {
            $operation = ClientOperation::query()->create([
                'user_id' => $user->getKey(),
                'operation_key' => $key,
                'request_hash' => $hash,
                'resource_type' => $resourceType,
            ]);
        } catch (QueryException) {
            $operation = ClientOperation::query()
                ->where('user_id', $user->getKey())
                ->where('operation_key', $key)
                ->firstOrFail();

            return $this->replay($operation, $hash);
        }

        try {
            /** @var Response $response */
            $response = $next($request);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
                $operation->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => is_array($body) ? $body : [],
                    'resource_id' => data_get($body, 'data.id'),
                ]);
            } else {
                $operation->delete();
            }

            return $response;
        } catch (\Throwable $exception) {
            $operation->delete();
            throw $exception;
        }
    }

    private function replay(ClientOperation $operation, string $hash): JsonResponse
    {
        if (! hash_equals($operation->request_hash, $hash)) {
            throw new DomainConflictException('این کلید عملیات قبلاً برای درخواست دیگری استفاده شده است.');
        }
        if ($operation->response_status === null || $operation->response_body === null) {
            throw new DomainConflictException('این عملیات هم‌اکنون در حال پردازش است.');
        }

        return response()->json($operation->response_body, $operation->response_status)
            ->header('Idempotency-Replayed', 'true');
    }
}
