<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Statistikwert 0–10 in Schritten von 0,1. NULL ist erlaubt und bedeutet „unbekannt“. */
class StatValue implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }
        if (is_bool($value) || ! is_numeric($value)) {
            $fail('Der Wert muss eine Zahl sein.');

            return;
        }
        $number = (float) $value;
        if ($number < 0 || $number > 10) {
            $fail('Der Wert muss zwischen 0 und 10 liegen.');

            return;
        }
        if (abs($number * 10 - round($number * 10)) > 1e-9) {
            $fail('Erlaubt ist höchstens eine Nachkommastelle.');
        }
    }
}
