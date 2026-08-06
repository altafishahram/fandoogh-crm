<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\AgencyScope;
use App\Domain\Tenancy\Exceptions\ImmutableTenantException;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\Exceptions\TenantMismatchException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAgency
{
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new AgencyScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->hasAgency()) {
                $contextAgencyId = $context->agencyId();
                $modelAgencyId = $model->getAttribute('agency_id');

                if ($modelAgencyId !== null && (int) $modelAgencyId !== $contextAgencyId) {
                    throw new TenantMismatchException('این اطلاعات متعلق به آژانس دیگری است.');
                }

                $model->setAttribute('agency_id', $contextAgencyId);

                return;
            }

            $relatedAgency = $model->relationLoaded('agency')
                ? $model->getRelation('agency')
                : null;

            if (! $relatedAgency instanceof Agency
                || (int) $relatedAgency->getKey() !== (int) $model->getAttribute('agency_id')) {
                throw new MissingTenantContextException('برای ثبت این اطلاعات باید آژانس معتبر مشخص باشد.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('agency_id')) {
                throw new ImmutableTenantException('آژانس این رکورد قابل تغییر نیست.');
            }
        });
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
