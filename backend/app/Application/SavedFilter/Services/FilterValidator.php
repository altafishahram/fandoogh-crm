<?php

declare(strict_types=1);

namespace App\Application\SavedFilter\Services;

use App\Domain\SavedFilter\Enums\FilterModule;
use App\Domain\SavedFilter\Exceptions\InvalidFilterException;

final readonly class FilterValidator
{
    public function __construct(private FilterCatalog $catalog) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validate(FilterModule $module, array $filters): array
    {
        $unknown = array_diff(array_keys($filters), $this->catalog->fields($module));
        if ($unknown !== []) {
            throw new InvalidFilterException('یک یا چند فیلد انتخاب‌شده پشتیبانی نمی‌شود.');
        }

        foreach ($filters as $value) {
            $this->validateValue($value);
        }

        return $filters;
    }

    public function validateSort(FilterModule $module, ?string $sort): ?string
    {
        if ($sort === null || $sort === '') {
            return null;
        }

        $field = str_starts_with($sort, '-') ? substr($sort, 1) : $sort;
        if (! in_array($field, $this->catalog->sorts($module), true)) {
            throw new InvalidFilterException('مرتب‌سازی درخواستی پشتیبانی نمی‌شود.');
        }

        return $sort;
    }

    /** @param array<string, mixed> $filters
     * @return array{filters: array<string, mixed>, warnings: list<string>}
     */
    public function sanitizeStored(FilterModule $module, array $filters): array
    {
        $allowed = array_flip($this->catalog->fields($module));
        $sanitized = array_intersect_key($filters, $allowed);
        $warnings = [];

        foreach (array_diff(array_keys($filters), array_keys($sanitized)) as $unknown) {
            $warnings[] = 'Ignored unsupported filter field: '.$unknown;
        }

        foreach ($sanitized as $value) {
            $this->validateValue($value);
        }

        return ['filters' => $sanitized, 'warnings' => $warnings];
    }

    private function validateValue(mixed $value): void
    {
        if (! is_null($value) && ! is_scalar($value) && ! is_array($value)) {
            throw new InvalidFilterException('مقدار فیلتر باید ساده یا آرایه‌ای از مقادیر ساده باشد.');
        }

        $values = is_array($value) ? $value : [$value];
        foreach ($values as $item) {
            if (! is_null($item) && ! is_scalar($item)) {
                throw new InvalidFilterException('فیلترهای تودرتو پشتیبانی نمی‌شوند.');
            }
            if (is_string($item) && (str_contains($item, '://')
                || str_contains($item, "\0") || str_contains($item, ';') || str_contains($item, '--'))) {
                throw new InvalidFilterException('نشانی اینترنتی یا عبارت اجرایی مقدار معتبر فیلتر نیست.');
            }
        }
    }
}
