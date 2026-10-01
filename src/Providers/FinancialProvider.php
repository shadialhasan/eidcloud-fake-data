<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Providers;

/**
 * Generates realistic numbers, currencies, dates, booleans, and timestamps.
 */
class FinancialProvider
{
    public static function amount(float $min = 10.00, float $max = 50000.00, int $decimals = 2): float
    {
        $factor = 10 ** $decimals;
        return mt_rand((int) ($min * $factor), (int) ($max * $factor)) / $factor;
    }

    public static function currency(): string
    {
        $currencies = ['SAR', 'AED', 'USD', 'EUR', 'GBP', 'KWD', 'QAR', 'BHD'];
        return $currencies[array_rand($currencies)];
    }

    public static function paymentMethod(): string
    {
        $methods = ['Mada', 'Visa', 'Mastercard', 'Apple Pay', 'STC Pay', 'Bank Transfer', 'Cash on Delivery'];
        return $methods[array_rand($methods)];
    }

    public static function date(string $startDate = '-2 years', string $endDate = 'now', string $format = 'Y-m-d'): string
    {
        $start = strtotime($startDate) ?: time() - 63072000;
        $end = strtotime($endDate) ?: time();
        $timestamp = mt_rand(min($start, $end), max($start, $end));
        return date($format, $timestamp);
    }

    public static function dateTime(string $startDate = '-2 years', string $endDate = 'now', string $format = 'Y-m-d H:i:s'): string
    {
        return self::date($startDate, $endDate, $format);
    }

    public static function status(array $statuses = ['pending', 'completed', 'processing', 'cancelled', 'shipped']): string
    {
        return $statuses[array_rand($statuses)];
    }
}
