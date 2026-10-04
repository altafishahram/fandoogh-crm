<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MatchNotification;

use App\Application\Matching\Services\RelatedMatchQuery;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

final class MatchNotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $version = (int) ($this->resource->getAttribute('version') ?? 1);
        $readVersion = $this->resource->getAttribute('read_version');

        return [
            'id' => $this->resource->getKey(),
            'version' => $version,
            'title' => $this->resource->getAttribute('title'),
            'body' => $this->resource->getAttribute('body'),
            'short_reason' => RelatedMatchQuery::shortReason($this->resource->getRelationValue('match')),
            'is_read' => $readVersion !== null && (int) $readVersion >= $version,
            'read_at' => self::dateValue($this->resource->getAttribute('read_at')),
            'created_at' => self::dateValue($this->resource->getAttribute('created_at')),
            'updated_at' => self::dateValue($this->resource->getAttribute('updated_at')),
            'match' => $this->matchData(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function matchData(): ?array
    {
        $match = $this->resource->getRelationValue('match');

        if (! $match instanceof Model) {
            return null;
        }

        return [
            'id' => $match->getKey(),
            'score' => self::value($match, ['score', 'total_score']),
            'financial_score' => self::value($match, ['financial_score']),
            'area_score' => self::value($match, ['area_score']),
            'features_score' => self::value($match, ['features_score', 'feature_score']),
            'details_score' => self::value($match, ['details_score']),
            'match_mode' => self::enumValue(self::value($match, ['match_mode', 'mode'])),
            'match_mode_label' => self::matchModeLabel(self::value($match, ['match_mode', 'mode'])),
            'property_rank' => self::value($match, ['property_rank', 'rank_for_property']),
            'customer_rank' => self::value($match, ['customer_rank', 'rank_for_customer']),
            'financial_range' => [
                'deposit_min' => self::value($match, ['matched_deposit_min']),
                'deposit_max' => self::value($match, ['matched_deposit_max']),
                'rent_min' => self::value($match, ['matched_rent_min']),
                'rent_max' => self::value($match, ['matched_rent_max']),
            ],
            'property' => self::propertyData($match->getRelationValue('property')),
            'customer' => self::customerData($match->getRelationValue('customer')),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function propertyData(mixed $property): ?array
    {
        if (! $property instanceof Model) {
            return null;
        }

        $coverImageUrl = null;
        if ($property->relationLoaded('images')) {
            $images = $property->getRelationValue('images');
            if ($images instanceof Collection) {
                $image = $images->firstWhere('is_cover', true)
                    ?? $images->sortBy('sort_order')->first();
                if ($image instanceof Model) {
                    $coverImageUrl = route('api.v1.properties.images.content', [
                        'property' => $property->getKey(),
                        'image' => $image->getKey(),
                    ]);
                }
            }
        }

        return [
            'id' => $property->getKey(),
            'code' => $property->getAttribute('code'),
            'title' => $property->getAttribute('title'),
            'transaction_type' => self::enumValue($property->getAttribute('transaction_type')),
            'property_type' => self::enumValue($property->getAttribute('property_type')),
            'city' => $property->getAttribute('city'),
            'district' => $property->getAttribute('district'),
            'cover_image_url' => $coverImageUrl,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function customerData(mixed $customer): ?array
    {
        if (! $customer instanceof Model) {
            return null;
        }

        $fullName = $customer->getAttribute('full_name');
        if (! is_string($fullName) || trim($fullName) === '') {
            $fullName = trim((string) $customer->getAttribute('first_name').' '.(string) $customer->getAttribute('last_name'));
        }

        return [
            'id' => $customer->getKey(),
            'full_name' => $fullName,
            'intent' => self::enumValue($customer->getAttribute('intent')),
            'desired_property_type' => self::enumValue($customer->getAttribute('desired_property_type')),
            'desired_city' => $customer->getAttribute('desired_city'),
            'desired_district' => $customer->getAttribute('desired_district'),
        ];
    }

    /** @param list<string> $names */
    private static function value(Model $model, array $names): mixed
    {
        foreach ($names as $name) {
            $value = $model->getAttribute($name);
            if ($value !== null) {
                return self::enumValue($value);
            }
        }

        return null;
    }

    private static function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private static function matchModeLabel(mixed $mode): ?string
    {
        return match (self::enumValue($mode)) {
            'direct' => 'مستقیم',
            'converted', 'conversion' => 'با تبدیل ودیعه و اجاره',
            default => $mode === null ? null : (string) self::enumValue($mode),
        };
    }

    private static function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toISOString();
        }

        return (string) $value;
    }
}
