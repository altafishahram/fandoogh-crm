<?php

declare(strict_types=1);

namespace App\Application\SavedFilter\Services;

use App\Application\SavedFilter\Data\SavedFilterData;
use App\Domain\SavedFilter\Exceptions\InvalidFilterException;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SavedFilterService
{
    public function __construct(private FilterValidator $validator) {}

    public function create(User $user, SavedFilterData $data): SavedFilter
    {
        return DB::transaction(function () use ($user, $data): SavedFilter {
            $count = SavedFilter::query()
                ->where('user_id', $user->getKey())
                ->where('module', $data->module)
                ->lockForUpdate()
                ->count();
            if ($count >= 50) {
                throw new DomainConflictException('هر کاربر در هر بخش حداکثر می‌تواند ۵۰ فیلتر ذخیره کند.');
            }

            $this->assertUniqueName($user, $data);
            $filters = $this->validator->validate($data->module, $data->filters);
            $sort = $this->validator->validateSort($data->module, $data->sort);
            if ($data->isDefault) {
                $this->clearDefault($user, $data->module->value);
            }

            return SavedFilter::query()->create([
                'user_id' => $user->getKey(), 'module' => $data->module,
                'name' => trim($data->name), 'filters' => $filters,
                'sort' => $sort, 'is_default' => $data->isDefault,
            ]);
        });
    }

    public function update(User $user, SavedFilter $filter, SavedFilterData $data): SavedFilter
    {
        return DB::transaction(function () use ($user, $filter, $data): SavedFilter {
            if ($filter->user_id !== $user->getKey()) {
                throw new DomainConflictException('این فیلتر ذخیره‌شده متعلق به کاربر فعلی نیست.');
            }
            $this->assertUniqueName($user, $data, (int) $filter->getKey());
            $filters = $this->validator->validate($data->module, $data->filters);
            $sort = $this->validator->validateSort($data->module, $data->sort);
            if ($data->isDefault) {
                $this->clearDefault($user, $data->module->value, (int) $filter->getKey());
            }

            $filter->fill([
                'module' => $data->module, 'name' => trim($data->name),
                'filters' => $filters, 'sort' => $sort, 'is_default' => $data->isDefault,
            ])->save();

            return $filter->refresh();
        });
    }

    public function delete(User $user, SavedFilter $filter): void
    {
        if ($filter->user_id !== $user->getKey()) {
            throw new DomainConflictException('این فیلتر ذخیره‌شده متعلق به کاربر فعلی نیست.');
        }
        $filter->delete();
    }

    /** @return array{filters: array<string, mixed>, warnings: list<string>} */
    public function load(SavedFilter $filter): array
    {
        return $this->validator->sanitizeStored($filter->module, $filter->filters);
    }

    private function assertUniqueName(User $user, SavedFilterData $data, ?int $exceptId = null): void
    {
        $query = SavedFilter::query()
            ->where('user_id', $user->getKey())
            ->where('module', $data->module)
            ->where('name', trim($data->name));
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw new InvalidFilterException('نام فیلترهای ذخیره‌شده در هر بخش باید یکتا باشد.');
        }
    }

    private function clearDefault(User $user, string $module, ?int $exceptId = null): void
    {
        $query = SavedFilter::query()
            ->where('user_id', $user->getKey())->where('module', $module);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        $query->update(['is_default' => false]);
    }
}
