<?php

namespace App\Support;

/** 230000 -> "Two hundred thirty thousand". No intl extension needed. */
class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    private const SCALES = [1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'];

    public static function make(float $amount): string
    {
        $whole = (int) floor(round($amount, 2));
        if ($whole === 0) {
            return 'Zero';
        }

        $parts = [];
        foreach (self::SCALES as $value => $name) {
            if ($whole >= $value) {
                $parts[] = self::belowThousand(intdiv($whole, $value)) . ' ' . $name;
                $whole %= $value;
            }
        }
        if ($whole > 0) {
            $parts[] = self::belowThousand($whole);
        }

        return ucfirst(implode(' ', $parts));
    }

    private static function belowThousand(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::ONES[intdiv($n, 100)] . ' hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $words[] = self::TENS[intdiv($n, 10)] . ($n % 10 ? '-' . self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $words[] = self::ONES[$n];
        }

        return implode(' ', $words);
    }
}
