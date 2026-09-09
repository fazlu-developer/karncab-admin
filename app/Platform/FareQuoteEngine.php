<?php

namespace App\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side fare math from fare_rules. Matches Nest FareEngine.
 * Clients may send couponCode only — never totals or discount paise.
 */
final class FareQuoteEngine
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $product = RideCatalog::assertProduct((string) ($input['product'] ?? ''));
        $category = RideCatalog::assertCategory((string) ($input['category'] ?? ''));
        $hours = $product === 'RENTAL' ? (int) ($input['hours'] ?? 8) : (isset($input['hours']) ? (int) $input['hours'] : null);
        $rule = $this->findRule($product, $category, isset($input['districtId']) ? (int) $input['districtId'] : null, $hours);
        abort_unless($rule, 404, 'No fare rule for that product and vehicle');

        $minKm = (float) $rule->min_km;
        $includedKm = (float) $rule->included_km;
        $billedKm = $this->billedKm($input, $product, $minKm);
        $extraKm = max(0, $billedKm - $includedKm);
        $packagePaise = (int) round($includedKm * (int) $rule->per_km_paise);
        $basePaise = (int) round(min($minKm, $includedKm) * (int) $rule->per_km_paise);
        $distancePaise = $packagePaise - $basePaise;
        $extraPaise = (int) round($extraKm * (int) $rule->extra_km_paise);
        $waitingPaise = ((int) ($input['waitMinutes'] ?? 0)) * (int) $rule->waiting_paise_per_min;
        $extraHourPaiseRate = (int) ($rule->extra_hour_paise ?? 15000);
        $extraHours = $product === 'RENTAL' ? max(0, (int) ($input['extraHours'] ?? 0)) : 0;
        $rentalExtraPaise = $extraHours * $extraHourPaiseRate;
        $perStopPaise = (int) ($rule->stop_paise ?: ($product === 'MULTI_STOP' ? 1500 : 0));
        $stopCount = $product === 'MULTI_STOP' ? max(0, (int) ($input['stopCount'] ?? 0)) : 0;
        $stopPaise = $stopCount * $perStopPaise;
        $nights = $product === 'ROUND_WAY' ? max(0, (int) ($input['nightStayNights'] ?? 0)) : 0;
        $nightStayPaise = $nights * (int) ($rule->night_stay_paise ?? 0);
        $driverAllowPaise = $product === 'ROUND_WAY' ? (int) $rule->driver_allow_paise : 0;
        $preNight = $basePaise + $distancePaise + $extraPaise + $waitingPaise + $driverAllowPaise + $nightStayPaise + $rentalExtraPaise + $stopPaise;
        $night = filter_var($input['night'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $nightPaise = $night ? (int) round(($preNight * (int) $rule->night_percent) / 100) : 0;
        $taxable = $preNight + $nightPaise;
        $toll = $rule->apply_toll ? (int) ($input['tollPaise'] ?? 0) : 0;
        $parking = $rule->apply_parking ? (int) ($input['parkingPaise'] ?? 0) : 0;
        $gstBase = $rule->apply_gst_to_base ? $taxable : 0;
        $gstPaise = (int) round(($gstBase * (int) $rule->gst_percent) / 100);
        $beforeDiscount = $taxable + $toll + $parking + $gstPaise;
        $discountPaise = $this->discountPaise($rule, $beforeDiscount);
        $totalPaise = max(0, $beforeDiscount - $discountPaise);

        $breakdown = [
            'basePaise' => $basePaise,
            'distancePaise' => $distancePaise,
            'extraPaise' => $extraPaise,
            'waitingPaise' => $waitingPaise,
            'nightPaise' => $nightPaise,
            'driverAllowPaise' => $driverAllowPaise,
            'nightStayPaise' => $nightStayPaise,
            'rentalExtraPaise' => $rentalExtraPaise,
            'stopPaise' => $stopPaise,
            'nightPercent' => $night ? (int) $rule->night_percent : 0,
            'gstPaise' => $gstPaise,
            'tollPaise' => $toll,
            'parkingPaise' => $parking,
            'discountPaise' => $discountPaise,
            'cancelPaise' => (int) $rule->cancel_paise,
        ];
        $settled = $this->commission($breakdown, $totalPaise);
        $lines = $this->linesFor($product, $breakdown + [
            'totalPaise' => $totalPaise,
            'gstPercent' => (int) $rule->gst_percent,
            'billedKm' => $billedKm,
            'extraKm' => $extraKm,
            'minKm' => $minKm,
            'includedKm' => $includedKm,
            'nights' => $nights,
            'packagePaise' => $packagePaise,
            'rentalHours' => $rule->rental_hours,
            'extraHours' => $extraHours,
            'stopCount' => $stopCount,
        ]);
        $breakdown['lines'] = $lines;

        return [
            'currency' => 'INR',
            'product' => $product,
            'category' => $category,
            'billedKm' => $billedKm,
            'extraKm' => $extraKm,
            'hours' => $rule->rental_hours ?? $hours,
            'extraHours' => $extraHours,
            'nightStayNights' => $nights,
            'source' => 'server',
            'rates' => [
                'minKm' => $minKm,
                'includedKm' => $includedKm,
                'perKmPaise' => (int) $rule->per_km_paise,
                'extraKmPaise' => (int) $rule->extra_km_paise,
                'waitingPaisePerMin' => (int) $rule->waiting_paise_per_min,
                'nightPercent' => (int) $rule->night_percent,
                'gstPercent' => (int) $rule->gst_percent,
                'cancelPaise' => (int) $rule->cancel_paise,
                'discountPaise' => (int) ($rule->discount_paise ?? 0),
                'discountPercent' => (int) ($rule->discount_percent ?? 0),
                'driverAllowPaise' => (int) $rule->driver_allow_paise,
                'nightStayPaise' => (int) ($rule->night_stay_paise ?? 0),
                'extraHourPaise' => $extraHourPaiseRate,
                'rentalHours' => $rule->rental_hours,
                'stopPaise' => $perStopPaise,
                'applyToll' => (bool) $rule->apply_toll,
                'applyParking' => (bool) $rule->apply_parking,
                'applyGstToBase' => (bool) $rule->apply_gst_to_base,
            ],
            'breakdown' => $breakdown,
            'lines' => $lines,
            'totalPaise' => $totalPaise,
            'totalRupees' => $totalPaise / 100,
            'listRupees' => $beforeDiscount / 100,
            'discountRupees' => $discountPaise / 100,
            'commissionPercent' => $settled['percent'],
            'commissionPaise' => $settled['commissionPaise'],
            'commission' => $settled,
            'note' => 'Quote from admin fare_rules. Driver is not assigned.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rentalPackages(?int $districtId = null): array
    {
        if (! Schema::connection('platform')->hasTable('fare_rules')) {
            return [];
        }
        $q = DB::connection('platform')->table('fare_rules')->where('product', 'RENTAL')->where('active', true);
        if ($districtId) {
            $q->where(function ($inner) use ($districtId) {
                $inner->where('district_id', $districtId)->orWhereNull('district_id');
            });
        }

        return $q->orderBy('rental_hours')->orderBy('category')->get()->map(fn ($row) => [
            'hours' => $row->rental_hours,
            'category' => $row->category,
            'includedKm' => (float) $row->included_km,
            'pricePaise' => (int) round(((float) $row->included_km) * (int) $row->per_km_paise),
            'priceRupees' => ((int) round(((float) $row->included_km) * (int) $row->per_km_paise)) / 100,
            'extraKmPaise' => (int) $row->extra_km_paise,
            'extraHourPaise' => (int) ($row->extra_hour_paise ?? 15000),
        ])->all();
    }

    private function findRule(string $product, string $category, ?int $districtId, ?int $hours): ?object
    {
        if (! Schema::connection('platform')->hasTable('fare_rules')) {
            return null;
        }
        $q = DB::connection('platform')->table('fare_rules')
            ->where('product', $product)
            ->where('category', $category)
            ->where('active', true);
        if ($districtId) {
            $q->where(function ($inner) use ($districtId) {
                $inner->where('district_id', $districtId)->orWhereNull('district_id');
            });
        }
        if ($product === 'RENTAL' && $hours) {
            $q->where('rental_hours', $hours);
        }

        return $q->orderByDesc('district_id')->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function billedKm(array $input, string $product, float $minKm): float
    {
        $km = max((float) ($input['distanceKm'] ?? 0), $minKm);
        $roundTrip = $product === 'ROUND_WAY' && (($input['roundTrip'] ?? true) !== false);
        if ($roundTrip) {
            $km = max($km * 2, $minKm);
        }

        return $km;
    }

    private function discountPaise(object $rule, int $beforeDiscount): int
    {
        $flat = (int) ($rule->discount_paise ?? 0);
        $percent = (int) round(($beforeDiscount * ((int) ($rule->discount_percent ?? 0))) / 100);

        return min($beforeDiscount, $flat + $percent);
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<string, mixed>
     */
    private function commission(array $breakdown, int $totalPaise): array
    {
        $buckets = CommissionEngine::bucketsFromBreakdown($breakdown);
        if (! Schema::connection('platform')->hasTable('commission_rules')) {
            return ['percent' => null, 'commissionPaise' => 0, 'eligiblePaise' => 0, 'netPaise' => $totalPaise, 'buckets' => $buckets];
        }
        $rule = DB::connection('platform')->table('commission_rules')->where('active', true)->where('name', 'default')->first();
        if (! $rule || CommissionEngine::percentFromRule($rule) === null) {
            return ['percent' => null, 'commissionPaise' => 0, 'eligiblePaise' => 0, 'netPaise' => $totalPaise, 'buckets' => $buckets];
        }

        return CommissionEngine::settle($buckets, $rule, $totalPaise);
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return list<array<string, mixed>>
     */
    private function linesFor(string $product, array $parts): array
    {
        if ($product === 'RENTAL') {
            return [
                $this->line('package', 'Package', (int) $parts['packagePaise'], ['km' => $parts['includedKm'], 'hours' => $parts['rentalHours'] ?? null]),
                $this->line('extraKm', 'Extra KM', (int) $parts['extraPaise'], ['km' => $parts['extraKm']]),
                $this->line('extraHour', 'Extra hour', (int) $parts['rentalExtraPaise'], ['hours' => $parts['extraHours']]),
                $this->line('toll', 'Toll', (int) $parts['tollPaise']),
                $this->line('parking', 'Parking', (int) $parts['parkingPaise']),
                $this->line('gst', 'GST', (int) $parts['gstPaise'], ['percent' => $parts['gstPercent']]),
                $this->line('discount', 'Discount', -((int) $parts['discountPaise'])),
                $this->line('total', 'Total', (int) $parts['totalPaise'], ['includedInTotal' => false]),
            ];
        }

        return [
            $this->line('base', 'Base', (int) $parts['basePaise'], ['km' => min($parts['minKm'], $parts['includedKm'])]),
            $this->line('distance', 'Distance', (int) $parts['distancePaise'], ['km' => $parts['billedKm']]),
            $this->line('extraKm', 'Extra KM', (int) $parts['extraPaise'], ['km' => $parts['extraKm']]),
            $this->line('waiting', 'Waiting', (int) $parts['waitingPaise']),
            $this->line('night', 'Night', (int) $parts['nightPaise'], ['percent' => $parts['nightPercent']]),
            $this->line('toll', 'Toll', (int) $parts['tollPaise']),
            $this->line('parking', 'Parking', (int) $parts['parkingPaise']),
            $this->line('gst', 'GST', (int) $parts['gstPaise'], ['percent' => $parts['gstPercent']]),
            $this->line('discount', 'Discount', -((int) $parts['discountPaise'])),
            $this->line('total', 'Total', (int) $parts['totalPaise'], ['includedInTotal' => false]),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function line(string $key, string $label, int $paise, array $extra = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'paise' => $paise,
            'rupees' => $paise / 100,
            'km' => $extra['km'] ?? null,
            'hours' => $extra['hours'] ?? null,
            'percent' => $extra['percent'] ?? null,
            'includedInTotal' => $extra['includedInTotal'] ?? true,
        ];
    }
}
