<?php

declare(strict_types=1);

namespace App\Application\Matching\Services;

use App\Application\Matching\Data\MatchCandidate;
use App\Application\Property\Services\PropertyPricingCalculator;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Matching\Enums\MatchMode;
use App\Domain\Property\Enums\DeliveryStatus;
use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Domain\Tenancy\AgencyScope;
use App\Domain\Tenancy\TenantContext;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\MatchNotification;
use App\Models\MatchNotificationRead;
use App\Models\Property;
use App\Models\PropertyCustomerMatch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PropertyCustomerMatchingService
{
    private const MAX_MATCHES = 20;

    private const AREA_WEIGHT = 25.0;

    private const FEATURE_WEIGHT = 20.0;

    private const DIRECT_FINANCIAL_BONUS = 5.0;

    private const CONVERSION_RATE = 0.03;

    public function __construct(
        private readonly PropertyPricingCalculator $pricing,
        private readonly TenantContext $tenant,
    ) {}

    public function rebuildForAgency(int $agencyId): void
    {
        $agency = Agency::query()->find($agencyId);

        if ($agency === null || ! $agency->is_active) {
            $this->deleteMatches($agencyId);

            return;
        }

        $properties = Property::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agencyId)
            ->where('status', PropertyStatus::Available->value)
            ->whereNotNull('matching_eligible_at')
            ->get();
        $customers = Customer::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agencyId)
            ->where('status', CustomerStatus::Active->value)
            ->whereNotNull('matching_eligible_at')
            ->get();

        $candidates = [];
        foreach ($properties as $property) {
            foreach ($customers as $customer) {
                $candidate = $this->calculate($property, $customer);
                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            }
        }

        $propertyRanks = $this->rankByProperty($candidates);
        $customerRanks = $this->rankByCustomer($candidates);
        $keep = [];

        foreach ($candidates as $candidate) {
            $key = $this->pairKey($candidate->propertyId, $candidate->customerId);
            $propertyRank = $propertyRanks[$key] ?? null;
            $customerRank = $customerRanks[$key] ?? null;

            if ($propertyRank === null && $customerRank === null) {
                continue;
            }

            $keep[$key] = [$candidate, $propertyRank, $customerRank];
        }

        $this->tenant->runForAgency($agency, function () use ($agency, $keep, $properties, $customers): void {
            $this->persist(
                $agency,
                $keep,
                $properties->keyBy(fn (Property $property): int => (int) $property->getKey()),
                $customers->keyBy(fn (Customer $customer): int => (int) $customer->getKey()),
            );
        });
    }

    public function calculate(Property $property, Customer $customer): ?MatchCandidate
    {
        if ((int) $property->agency_id !== (int) $customer->agency_id
            || $this->propertyStatus($property) !== PropertyStatus::Available->value
            || $this->customerStatus($customer) !== CustomerStatus::Active->value
            || $property->matching_eligible_at === null
            || $customer->matching_eligible_at === null) {
            return null;
        }

        $propertyType = $this->enumValue($property->property_type);
        $desiredType = $this->enumValue($customer->desired_property_type);
        if ($propertyType === null || $desiredType === null || $propertyType !== $desiredType) {
            return null;
        }

        $intent = $this->enumValue($customer->intent);
        $transaction = $this->enumValue($property->transaction_type);
        if (($intent === 'buy' && $transaction !== TransactionType::Sale->value)
            || ($intent === 'rent' && $transaction !== TransactionType::Rent->value)) {
            return null;
        }

        if (! $this->locationMatches($property, $customer)) {
            return null;
        }

        $financial = $transaction === TransactionType::Sale->value
            ? $this->saleFinancialMatch($property, $customer)
            : $this->rentFinancialMatch($property, $customer);
        if ($financial === null) {
            return null;
        }

        $area = $this->areaMatch($property, $customer, $propertyType);
        if ($area === null || ! $this->featuresMatch($property, $customer)) {
            return null;
        }

        $featureScore = $this->featureScore($property, $customer);
        $score = min(
            100.0,
            max(0.0, $financial['score'] + $area['score'] + $featureScore),
        );

        $fingerprint = hash('sha256', (string) json_encode([
            'property_id' => (int) $property->getKey(),
            'customer_id' => (int) $customer->getKey(),
            'property_eligible_at' => $this->raw($property, 'matching_eligible_at'),
            'customer_eligible_at' => $this->raw($customer, 'matching_eligible_at'),
            'score' => round($score, 2),
            'financial_score' => round($financial['score'], 2),
            'area_score' => round($area['score'], 2),
            'feature_score' => round($featureScore, 2),
            'mode' => $financial['mode']->value,
            'matched_deposit_min' => $financial['deposit_min'],
            'matched_deposit_max' => $financial['deposit_max'],
            'matched_rent_min' => $financial['rent_min'],
            'matched_rent_max' => $financial['rent_max'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return new MatchCandidate(
            propertyId: (int) $property->getKey(),
            customerId: (int) $customer->getKey(),
            score: round($score, 2),
            financialScore: round($financial['score'], 2),
            areaScore: round($area['score'], 2),
            featureScore: round($featureScore, 2),
            mode: $financial['mode'],
            matchedDepositMin: $financial['deposit_min'],
            matchedDepositMax: $financial['deposit_max'],
            matchedRentMin: $financial['rent_min'],
            matchedRentMax: $financial['rent_max'],
            fingerprint: $fingerprint,
            propertyCreatedTimestamp: $this->createdTimestamp($property),
            customerCreatedTimestamp: $this->createdTimestamp($customer),
        );
    }

    /** @param list<MatchCandidate> $candidates
     * @return array<string, int>
     */
    private function rankByProperty(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate->propertyId][] = $candidate;
        }

        return $this->rankGroups($groups, true);
    }

    /** @param list<MatchCandidate> $candidates
     * @return array<string, int>
     */
    private function rankByCustomer(array $candidates): array
    {
        $groups = [];
        foreach ($candidates as $candidate) {
            $groups[$candidate->customerId][] = $candidate;
        }

        return $this->rankGroups($groups, false);
    }

    /** @param array<int, list<MatchCandidate>> $groups
     * @return array<string, int>
     */
    private function rankGroups(array $groups, bool $byProperty): array
    {
        $ranks = [];
        foreach ($groups as $candidates) {
            usort($candidates, function (MatchCandidate $left, MatchCandidate $right) use ($byProperty): int {
                $scoreComparison = $right->score <=> $left->score;
                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                $leftTimestamp = $byProperty
                    ? $left->customerCreatedTimestamp
                    : $left->propertyCreatedTimestamp;
                $rightTimestamp = $byProperty
                    ? $right->customerCreatedTimestamp
                    : $right->propertyCreatedTimestamp;
                $timestampComparison = $rightTimestamp <=> $leftTimestamp;
                if ($timestampComparison !== 0) {
                    return $timestampComparison;
                }

                $leftId = $byProperty ? $left->customerId : $left->propertyId;
                $rightId = $byProperty ? $right->customerId : $right->propertyId;

                return $rightId <=> $leftId;
            });

            foreach (array_slice($candidates, 0, self::MAX_MATCHES) as $index => $candidate) {
                $ranks[$this->pairKey($candidate->propertyId, $candidate->customerId)] = $index + 1;
            }
        }

        return $ranks;
    }

    /** @param array<string, array{0: MatchCandidate, 1: ?int, 2: ?int}> $keep
     * @param  Collection<int, Property>  $properties
     * @param  Collection<int, Customer>  $customers
     */
    private function persist(Agency $agency, array $keep, Collection $properties, Collection $customers): void
    {
        DB::transaction(function () use ($agency, $keep, $properties, $customers): void {
            $existing = PropertyCustomerMatch::query()
                ->withoutGlobalScope(AgencyScope::class)
                ->where('agency_id', $agency->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (PropertyCustomerMatch $match): string => $this->pairKey(
                    (int) $match->property_id,
                    (int) $match->customer_id,
                ));

            foreach ($keep as $key => [$candidate, $propertyRank, $customerRank]) {
                $rankedCandidate = $candidate->withRanks($propertyRank, $customerRank);
                $attributes = $rankedCandidate->attributes((int) $agency->getKey());
                $match = $existing->get($key);
                $isNew = $match === null;
                $fingerprintChanged = $isNew || $match->fingerprint !== $attributes['fingerprint'];

                if ($isNew) {
                    $match = new PropertyCustomerMatch;
                    $match->setRelation('agency', $agency);
                }

                $match->forceFill($attributes);
                $match->save();

                $property = $properties->get($candidate->propertyId);
                $customer = $customers->get($candidate->customerId);
                if ($property instanceof Property && $customer instanceof Customer) {
                    $this->persistNotification($agency, $match, $property, $customer, $fingerprintChanged);
                }
            }

            $existing
                ->reject(fn (PropertyCustomerMatch $match, string $key): bool => array_key_exists($key, $keep))
                ->each(static fn (PropertyCustomerMatch $match): bool => (bool) $match->delete());
        }, 3);
    }

    private function persistNotification(
        Agency $agency,
        PropertyCustomerMatch $match,
        Property $property,
        Customer $customer,
        bool $changed,
    ): void {
        $notification = MatchNotification::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agency->getKey())
            ->where('property_customer_match_id', $match->getKey())
            ->first();
        $body = $this->notificationBody($property, $customer, (float) $match->score);

        if ($notification === null) {
            $notification = new MatchNotification;
            $notification->setRelation('agency', $agency);
            $notification->forceFill([
                'agency_id' => $agency->getKey(),
                'property_customer_match_id' => $match->getKey(),
                'version' => 1,
                'title' => 'تطبیق جدید ملک و مشتری',
                'body' => $body,
            ]);
            $notification->save();

            return;
        }

        if (! $changed) {
            return;
        }

        $notification->forceFill([
            'version' => (int) $notification->version + 1,
            'title' => 'به‌روزرسانی تطبیق ملک و مشتری',
            'body' => $body,
        ]);
        $notification->save();

        MatchNotificationRead::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agency->getKey())
            ->where('match_notification_id', $notification->getKey())
            ->delete();
    }

    private function notificationBody(Property $property, Customer $customer, float $score): string
    {
        $customerName = trim((string) ($customer->full_name ?: ($customer->first_name.' '.$customer->last_name)));
        $propertyTitle = trim((string) $property->title);

        return sprintf(
            'ملک «%s» با نیاز «%s» تطبیق داده شد؛ امتیاز تطبیق %s از ۱۰۰ است.',
            $propertyTitle !== '' ? $propertyTitle : 'بدون عنوان',
            $customerName !== '' ? $customerName : 'بدون نام',
            $this->persianNumber((string) (int) round($score)),
        );
    }

    private function deleteMatches(int $agencyId): void
    {
        PropertyCustomerMatch::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->where('agency_id', $agencyId)
            ->delete();
    }

    private function locationMatches(Property $property, Customer $customer): bool
    {
        if ($customer->desired_city_id !== null && $property->city_id !== null
            && (int) $customer->desired_city_id !== (int) $property->city_id) {
            return false;
        }
        foreach ([['desired_city', 'city'], ['desired_district', 'district']] as [$customerField, $propertyField]) {
            $wanted = $this->normalText($customer->getAttribute($customerField));
            if ($wanted !== '' && $wanted !== $this->normalText($property->getAttribute($propertyField))) {
                return false;
            }
        }

        return true;
    }

    /** @return array{score: float}|null */
    private function areaMatch(Property $property, Customer $customer, string $propertyType): ?array
    {
        if ($propertyType === PropertyType::Industrial->value) {
            $landRange = $this->positiveRange($customer->land_area_min, $customer->land_area_max);
            $buildingRange = $this->positiveRange($customer->building_area_min, $customer->building_area_max);
            $land = $this->number($property->land_area);
            $building = $this->number($property->building_area);

            if ($landRange === null || $buildingRange === null || $land === null || $building === null
                || ! $this->inRange($land, $landRange) || ! $this->inRange($building, $buildingRange)) {
                return null;
            }

            return [
                'score' => self::AREA_WEIGHT * (
                    ($this->closenessToMaximum($land, $landRange) + $this->closenessToMaximum($building, $buildingRange)) / 2
                ),
            ];
        }

        $range = $this->positiveRange($customer->min_area_sqm, $customer->max_area_sqm);
        $area = $this->number($property->area_sqm);
        if ($range === null || $area === null || ! $this->inRange($area, $range)) {
            return null;
        }

        return ['score' => self::AREA_WEIGHT * $this->closenessToMaximum($area, $range)];
    }

    private function featuresMatch(Property $property, Customer $customer): bool
    {
        $minimumBedrooms = $this->number($customer->min_bedrooms);
        if ($minimumBedrooms !== null
            && ($minimumBedrooms < 0 || $minimumBedrooms !== floor($minimumBedrooms))) {
            return false;
        }
        if ($minimumBedrooms !== null
            && ($this->number($property->bedrooms) === null || (float) $property->bedrooms < $minimumBedrooms)) {
            return false;
        }

        $minimumParking = $this->number($customer->min_parking_spaces);
        if ($minimumParking !== null
            && ($minimumParking < 0 || $minimumParking !== floor($minimumParking))) {
            return false;
        }
        if ($minimumParking !== null
            && ($this->number($property->parking_spaces) === null || (float) $property->parking_spaces < $minimumParking)) {
            return false;
        }

        if ((bool) $customer->has_parking && (int) ($property->parking_spaces ?? 0) < 1) {
            return false;
        }

        foreach ([
            'has_storage_room', 'has_elevator', 'has_balcony', 'has_master_bathroom',
            'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            'has_water', 'has_electricity', 'has_gas',
        ] as $field) {
            if ((bool) $customer->getAttribute($field) && ! (bool) $property->getAttribute($field)) {
                return false;
            }
        }

        if ((bool) $customer->owner_resides
            && $this->enumValue($property->delivery_status) !== DeliveryStatus::OwnerOccupied->value) {
            return false;
        }

        $wantedToilets = $this->stringList($customer->toilet_types);
        if ($wantedToilets !== [] && array_diff($wantedToilets, $this->stringList($property->toilet_types)) !== []) {
            return false;
        }

        return true;
    }

    private function featureScore(Property $property, Customer $customer): float
    {
        $score = 0.0;
        $minimumParking = $this->number($customer->min_parking_spaces) ?? 0.0;
        if ((bool) $customer->has_parking || $minimumParking > 0) {
            $score += 5.0;
        }
        if ((bool) $customer->has_storage_room) {
            $score += 5.0;
        }
        if ((bool) $customer->has_elevator) {
            $score += 2.5;
        }
        if ((bool) $customer->has_balcony) {
            $score += 2.5;
        }

        $detailFields = [
            'cabinet_type', 'heating_type', 'cooling_type', 'flooring_type',
            'renovation_status', 'building_orientation', 'deed_type', 'building_type',
            'structure_type',
        ];
        $requested = 0;
        $matched = 0;
        foreach ($detailFields as $field) {
            $wanted = $this->normalText($customer->getAttribute($field));
            if ($wanted === '') {
                continue;
            }

            $requested++;
            if ($wanted === $this->normalText($property->getAttribute($field))) {
                $matched++;
            }
        }

        if ($requested > 0) {
            $score += 5.0 * $matched / $requested;
        }

        return min(self::FEATURE_WEIGHT, $score);
    }

    /** @return array{mode: MatchMode, score: float, deposit_min: ?float, deposit_max: ?float, rent_min: ?float, rent_max: ?float}|null */
    private function saleFinancialMatch(Property $property, Customer $customer): ?array
    {
        $range = $this->nonNegativeRange($customer->budget_min, $customer->budget_max);
        $price = $this->number($property->sale_price);
        if ($range === null || $range[1] <= 0 || $price === null || $price <= 0 || ! $this->inRange($price, $range)) {
            return null;
        }

        return [
            'mode' => MatchMode::Direct,
            'score' => self::DIRECT_FINANCIAL_BONUS + 50.0 * $this->closenessToMaximum($price, $range),
            'deposit_min' => null,
            'deposit_max' => null,
            'rent_min' => null,
            'rent_max' => null,
        ];
    }

    /** @return array{mode: MatchMode, score: float, deposit_min: ?float, deposit_max: ?float, rent_min: ?float, rent_max: ?float}|null */
    private function rentFinancialMatch(Property $property, Customer $customer): ?array
    {
        $depositMin = $this->numberOrZero($customer->rental_deposit_min);
        $depositMax = $this->numberOrZero($customer->rental_deposit_max);
        $rentMin = $this->numberOrZero($customer->rental_rent_min);
        $rentMax = $this->numberOrZero($customer->rental_rent_max);
        if ($depositMin < 0 || $rentMin < 0 || $depositMin > $depositMax || $rentMin > $rentMax
            || ($depositMax <= 0 && $rentMax <= 0)
            || ($depositMax <= 0 && $rentMax > 0)) {
            return null;
        }

        $deposit = $this->numberOrZero($property->deposit_amount);
        $rent = $this->numberOrZero($property->monthly_rent);
        if (($deposit <= 0 && $rent <= 0) || ($deposit <= 0 && $rent > 0)) {
            return null;
        }

        $direct = $deposit >= $depositMin
            && $deposit <= $depositMax
            && $rent >= $rentMin
            && $rent <= $rentMax;
        if ($direct) {
            return [
                'mode' => MatchMode::Direct,
                'score' => self::DIRECT_FINANCIAL_BONUS + 50.0 * $this->rentCloseness(
                    $deposit,
                    $rent,
                    $depositMin,
                    $depositMax,
                    $rentMin,
                    $rentMax,
                ),
                'deposit_min' => $deposit,
                'deposit_max' => $deposit,
                'rent_min' => $rent,
                'rent_max' => $rent,
            ];
        }

        if (! (bool) $property->is_convertible || ! (bool) $customer->accepts_rent_conversion) {
            return null;
        }

        $minimumDeposit = $this->number($property->minimum_deposit);
        if ($minimumDeposit === null || $minimumDeposit < 0 || $minimumDeposit > $deposit) {
            return null;
        }

        $intersection = $this->conversionIntersection(
            $deposit,
            $rent,
            $minimumDeposit,
            $depositMin,
            $depositMax,
            $rentMin,
            $rentMax,
        );
        if ($intersection === null) {
            return null;
        }

        [$matchedDepositMin, $matchedDepositMax] = $intersection;
        $matchedRentAtMinDeposit = $this->convertedRent($deposit, $rent, $matchedDepositMin);
        $matchedRentAtMaxDeposit = $this->convertedRent($deposit, $rent, $matchedDepositMax);
        $bestCloseness = max(
            $this->rentCloseness($matchedDepositMin, $matchedRentAtMinDeposit, $depositMin, $depositMax, $rentMin, $rentMax),
            $this->rentCloseness($matchedDepositMax, $matchedRentAtMaxDeposit, $depositMin, $depositMax, $rentMin, $rentMax),
        );

        return [
            'mode' => MatchMode::Converted,
            'score' => 50.0 * $bestCloseness,
            'deposit_min' => $matchedDepositMin,
            'deposit_max' => $matchedDepositMax,
            'rent_min' => min($matchedRentAtMinDeposit, $matchedRentAtMaxDeposit),
            'rent_max' => max($matchedRentAtMinDeposit, $matchedRentAtMaxDeposit),
        ];
    }

    /** @return array{0: float, 1: float}|null */
    private function conversionIntersection(
        float $initialDeposit,
        float $initialRent,
        float $minimumDeposit,
        float $customerDepositMin,
        float $customerDepositMax,
        float $customerRentMin,
        float $customerRentMax,
    ): ?array {
        $minimum = max($minimumDeposit, $customerDepositMin);
        $maximum = min($initialDeposit, $customerDepositMax);

        $minimum = max(
            $minimum,
            $initialDeposit - (($customerRentMax - $initialRent) / self::CONVERSION_RATE),
        );
        $maximum = min(
            $maximum,
            $initialDeposit - (($customerRentMin - $initialRent) / self::CONVERSION_RATE),
        );

        if ($minimum > $maximum + 0.000001) {
            return null;
        }

        return [max(0.0, $minimum), max(0.0, $maximum)];
    }

    private function rentCloseness(
        float $deposit,
        float $rent,
        float $depositMin,
        float $depositMax,
        float $rentMin,
        float $rentMax,
    ): float {
        return ($this->closenessToMaximum($deposit, [$depositMin, $depositMax])
            + $this->closenessToMaximum($rent, [$rentMin, $rentMax])) / 2;
    }

    private function convertedRent(float $initialDeposit, float $initialRent, float $deposit): float
    {
        return (float) $this->pricing->convertedRentAtDeposit(
            $this->decimal($initialDeposit),
            $this->decimal($initialRent),
            $this->decimal($deposit),
        );
    }

    /** @param array{0: float, 1: float} $range */
    private function inRange(float $value, array $range): bool
    {
        return $value >= $range[0] - 0.000001 && $value <= $range[1] + 0.000001;
    }

    /** @return array{0: float, 1: float}|null */
    private function positiveRange(mixed $minimum, mixed $maximum): ?array
    {
        $minimum = $this->number($minimum);
        $maximum = $this->number($maximum);
        if ($minimum === null || $maximum === null || $minimum <= 0 || $maximum <= 0 || $minimum > $maximum) {
            return null;
        }

        return [$minimum, $maximum];
    }

    /** @return array{0: float, 1: float}|null */
    private function nonNegativeRange(mixed $minimum, mixed $maximum): ?array
    {
        $minimum = $this->number($minimum);
        $maximum = $this->number($maximum);
        if ($minimum === null || $maximum === null || $minimum < 0 || $maximum < 0 || $minimum > $maximum) {
            return null;
        }

        return [$minimum, $maximum];
    }

    /** @param array{0: float, 1: float} $range */
    private function closenessToMaximum(float $value, array $range): float
    {
        if (abs($range[1] - $range[0]) < 0.000001) {
            return 1.0;
        }

        return min(1.0, max(0.0, ($value - $range[0]) / ($range[1] - $range[0])));
    }

    private function numberOrZero(mixed $value): float
    {
        return $this->number($value) ?? 0.0;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => '', '،' => '', ',' => '', ' ' => '',
        ]);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => $this->normalText($item),
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }

    private function normalText(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه']);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \BackedEnum
            ? (string) $value->value
            : (string) $value;
    }

    private function propertyStatus(Property $property): ?string
    {
        return $this->enumValue($property->status);
    }

    private function customerStatus(Customer $customer): ?string
    {
        return $this->enumValue($customer->status);
    }

    private function raw(object $model, string $field): mixed
    {
        return method_exists($model, 'getRawOriginal')
            ? $model->getRawOriginal($field)
            : $model->{$field};
    }

    private function createdTimestamp(Model $model): int
    {
        $createdAt = $model->getAttribute('created_at');
        if ($createdAt instanceof CarbonInterface) {
            return $createdAt->getTimestamp();
        }

        $timestamp = strtotime((string) $createdAt);

        return $timestamp === false ? 0 : $timestamp;
    }

    private function pairKey(int $propertyId, int $customerId): string
    {
        return $propertyId.':'.$customerId;
    }

    private function persianNumber(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
