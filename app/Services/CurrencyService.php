<?php

namespace App\Services;

use App\Models\Envelope;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Wishlist;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    /**
     * Default base rates against USD.
     *
     * @var array<string, float>
     */
    protected static array $defaultRatesAgainstUSD = [
        'USD' => 1.0,
        'SAR' => 3.75,
        'ILS' => 3.0634,
        'AED' => 3.6725,
        'JOD' => 0.709,
        'EGP' => 52.38,
        'KWD' => 0.3105,
        'QAR' => 3.64,
        'EUR' => 0.8924,
    ];

    /**
     * Supported currencies metadata.
     *
     * @var array<string, array{name: string, symbol: string, flag: string}>
     */
    protected static array $currencyMetadata = [
        'SAR' => ['name' => 'ريال سعودي', 'symbol' => 'ر.س', 'flag' => '🇸🇦'],
        'ILS' => ['name' => 'شيكل', 'symbol' => '₪', 'flag' => '🇵🇸'],
        'AED' => ['name' => 'درهم إماراتي', 'symbol' => 'د.إ', 'flag' => '🇦🇪'],
        'JOD' => ['name' => 'دينار أردني', 'symbol' => 'د.أ', 'flag' => '🇯🇴'],
        'EGP' => ['name' => 'جنيه مصري', 'symbol' => 'ج.م', 'flag' => '🇪🇬'],
        'KWD' => ['name' => 'دينار كويتي', 'symbol' => 'د.ك', 'flag' => '🇰🇼'],
        'QAR' => ['name' => 'ريال قطري', 'symbol' => 'ر.ق', 'flag' => '🇶🇦'],
        'USD' => ['name' => 'دولار أمريكي', 'symbol' => '$', 'flag' => '🇺🇸'],
        'EUR' => ['name' => 'يورو أوروبي', 'symbol' => '€', 'flag' => '🇪🇺'],
    ];

    public const CACHE_KEY = 'masroofi_exchange_rates_usd';

    public const CACHE_TIME_KEY = 'masroofi_exchange_rates_last_updated';

    public const CACHE_TTL_SECONDS = 3600; // 1 hour for high market accuracy

    /**
     * Retrieve exchange rates (base USD).
     *
     * @return array<string, float>
     */
    public static function getRates(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            return self::fetchLiveRatesAndCacheTime();
        });
    }

    /**
     * Force fetch fresh exchange rates from the live provider and refresh the cache.
     *
     * @return array<string, float>
     */
    public static function refreshRates(bool $force = true): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        $rates = self::fetchLiveRatesAndCacheTime();
        Cache::put(self::CACHE_KEY, $rates, self::CACHE_TTL_SECONDS);

        return $rates;
    }

    /**
     * Internal helper to fetch live rates from API and record timestamp.
     *
     * @return array<string, float>
     */
    protected static function fetchLiveRatesAndCacheTime(): array
    {
        try {
            $response = Http::timeout(4)->get('https://open.er-api.com/v6/latest/USD');
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates']) && is_array($data['rates'])) {
                    $rates = self::$defaultRatesAgainstUSD;
                    foreach (array_keys($rates) as $curr) {
                        if (isset($data['rates'][$curr]) && is_numeric($data['rates'][$curr])) {
                            $rates[$curr] = (float) $data['rates'][$curr];
                        }
                    }

                    Cache::put(self::CACHE_TIME_KEY, now()->toIso8601String(), self::CACHE_TTL_SECONDS * 24);

                    return $rates;
                }
            }
        } catch (\Throwable $e) {
            // Fallback gracefully on network timeout or failure
        }

        if (! Cache::has(self::CACHE_TIME_KEY)) {
            Cache::put(self::CACHE_TIME_KEY, now()->toIso8601String(), self::CACHE_TTL_SECONDS * 24);
        }

        return self::$defaultRatesAgainstUSD;
    }

    /**
     * Get the date and time when exchange rates were last updated.
     */
    public static function getLastUpdated(): string
    {
        $timestamp = Cache::get(self::CACHE_TIME_KEY);

        if ($timestamp) {
            try {
                return Carbon::parse($timestamp)->diffForHumans();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return 'محدث لحظياً الآن';
    }

    /**
     * Get currency metadata.
     *
     * @return array<string, array{name: string, symbol: string, flag: string}>
     */
    public static function getCurrencies(): array
    {
        return self::$currencyMetadata;
    }

    /**
     * Get exchange rate from one currency to another.
     */
    public static function getRate(string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $rates = self::getRates();
        $rateFrom = $rates[$from] ?? (self::$defaultRatesAgainstUSD[$from] ?? 1.0);
        $rateTo = $rates[$to] ?? (self::$defaultRatesAgainstUSD[$to] ?? 1.0);

        if ($rateFrom <= 0) {
            return 1.0;
        }

        return round($rateTo / $rateFrom, 4);
    }

    /**
     * Convert an amount between currencies.
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        $rate = self::getRate($from, $to);

        return round($amount * $rate, 2);
    }

    /**
     * Convert all user financial records from old currency to new currency.
     *
     * @return float The exchange rate used
     */
    public static function convertAllFinancialData(string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $rate = self::getRate($from, $to);

        // 1. User Settings
        $setting = UserSetting::firstOrCreate(['id' => 1]);
        $setting->timestamps = false;
        $setting->salary = round(((float) $setting->salary) * $rate, 2);
        $setting->saved_from_impulse = round(((float) $setting->saved_from_impulse) * $rate, 2);
        $setting->currency = $to;
        $setting->save();

        // 2. Envelopes
        Envelope::all()->each(function (Envelope $env) use ($rate): void {
            $env->timestamps = false;
            $env->budget = round(((float) $env->budget) * $rate, 2);
            $env->save();
        });

        // 3. Saving Goals
        SavingGoal::all()->each(function (SavingGoal $goal) use ($rate): void {
            $goal->timestamps = false;
            $goal->target_amount = round(((float) $goal->target_amount) * $rate, 2);
            $goal->current_amount = round(((float) $goal->current_amount) * $rate, 2);
            $goal->save();
        });

        // 4. Transactions
        Transaction::all()->each(function (Transaction $tx) use ($rate): void {
            $tx->timestamps = false;
            $tx->amount = round(((float) $tx->amount) * $rate, 2);
            $tx->save();
        });

        // 5. Subscriptions
        Subscription::all()->each(function (Subscription $sub) use ($rate): void {
            $sub->timestamps = false;
            $sub->cost = round(((float) $sub->cost) * $rate, 2);
            $sub->save();
        });

        // 6. Wishlist
        Wishlist::all()->each(function (Wishlist $wish) use ($rate): void {
            $wish->timestamps = false;
            $wish->price = round(((float) $wish->price) * $rate, 2);
            $wish->save();
        });

        return $rate;
    }
}
