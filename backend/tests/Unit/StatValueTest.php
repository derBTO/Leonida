<?php

namespace Tests\Unit;

use App\Rules\StatValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatValueTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'null = unbekannt' => [null, true],
            'null-Wert 0' => [0, true],
            'Grenze 10' => [10, true],
            'eine Nachkommastelle' => [2.5, true],
            'Text mit Nachkommastelle' => ['9.9', true],
            'zwei Nachkommastellen' => [2.55, false],
            'negativ' => [-0.1, false],
            'über 10' => [10.1, false],
            'kein Zahlwert' => ['abc', false],
            'boolean' => [true, false],
        ];
    }

    #[DataProvider('cases')]
    public function test_validates(mixed $value, bool $ok): void
    {
        $failed = false;
        (new StatValue)->validate('damage', $value, function () use (&$failed) {
            $failed = true;
        });
        $this->assertSame($ok, ! $failed);
    }
}
