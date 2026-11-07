<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExchangeRateConverter
{
    /**
     * Nigerian gateways charge in NGN when the invoice is still stored in USD.
     */
    public const LOCAL_CURRENCY = 'NGN';

    /**
     * @return array{currency: string, amount_cents: int, rate: float, major: string}
     */
    public function chargeForInvoice(int $amountCents, string $invoiceCurrency, string $targetCurrency = self::LOCAL_CURRENCY): array
    {
        $from = strtoupper($invoiceCurrency);
        $to = strtoupper($targetCurrency);

        if ($from === $to) {
            return [
                'currency' => $to,
                'amount_cents' => $amountCents,
                'rate' => 1.0,
                'major' => number_format($amountCents / 100, 2, '.', ''),
            ];
        }

        $rate = $this->rate($from, $to);
        $major = round(($amountCents / 100) * $rate, 2);
        $convertedCents = (int) round($major * 100);

        if ($convertedCents < 1) {
            throw new PaymentException(__('The converted charge amount is too small to process.'));
        }

        return [
            'currency' => $to,
            'amount_cents' => $convertedCents,
            'rate' => $rate,
            'major' => number_format($major, 2, '.', ''),
        ];
    }

    /**
     * Live mid-market rate from the free open ExchangeRate-API endpoint.
     */
    public function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->ratesFor($from);
        $rate = $rates[$to] ?? null;

        if (! is_numeric($rate) || (float) $rate <= 0) {
            throw new PaymentException(__('A live :from to :to exchange rate is unavailable right now. Try again shortly.', [
                'from' => $from,
                'to' => $to,
            ]));
        }

        return (float) $rate;
    }

    /**
     * Snapshot for the payments UI.
     *
     * @return array{from: string, to: string, rate: float, formatted: string, asOf: string|null, note: string}|null
     */
    public function snapshot(string $from = 'USD', string $to = self::LOCAL_CURRENCY): ?array
    {
        try {
            $rate = $this->rate($from, $to);
        } catch (PaymentException) {
            return null;
        }

        $asOf = Cache::get($this->cacheMetaKey(strtoupper($from)));

        return [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => $rate,
            'formatted' => '1 '.strtoupper($from).' ≈ '.number_format($rate, 2).' '.strtoupper($to),
            'asOf' => is_string($asOf) ? $asOf : null,
            'note' => __('USD invoices are charged in :currency at the live mid-market rate. Rates by ExchangeRate-API.', [
                'currency' => strtoupper($to),
            ]),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function ratesFor(string $base): array
    {
        $cacheKey = 'exchange-rates:'.Str::lower($base);

        /** @var array<string, float|int> $rates */
        $rates = Cache::remember($cacheKey, now()->addHours(6), function () use ($base) {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->acceptJson()
                ->get(rtrim((string) config('services.exchange_rate.url'), '/').'/'.$base);

            if ($response->failed() || $response->json('result') !== 'success') {
                Log::warning('Exchange rate lookup failed.', [
                    'base' => $base,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new PaymentException(__('Live exchange rates could not be loaded. Try again in a moment.'));
            }

            $rates = $response->json('rates');

            if (! is_array($rates) || $rates === []) {
                throw new PaymentException(__('Live exchange rates could not be loaded. Try again in a moment.'));
            }

            Cache::put(
                $this->cacheMetaKey($base),
                (string) ($response->json('time_last_update_utc') ?: now()->toRfc7231String()),
                now()->addHours(6),
            );

            return $rates;
        });

        return $rates;
    }

    protected function cacheMetaKey(string $base): string
    {
        return 'exchange-rates-as-of:'.Str::lower($base);
    }
}
