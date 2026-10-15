<?php

namespace Tests\Unit;

use App\Support\RaceTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RaceTimeTest extends TestCase
{
    public static function inputs(): array
    {
        return [
            ['1430', "14'30"],
            ['405', "4'05"],
            ['40530', "4'05\"30"],
            ['143012', "14'30\"12"],
            ['１４３０', "14'30"],
            ['１４’３０', "14'30"],
            ["14'30", "14'30"],
        ];
    }

    #[DataProvider('inputs')]
    public function test_digits_and_variants_become_the_sheet_format(string $input, string $expected): void
    {
        $this->assertSame($expected, RaceTime::normalize($input));
        $this->assertTrue(RaceTime::isValid($expected));
    }

    public function test_invalid_times_are_rejected(): void
    {
        $this->assertFalse(RaceTime::isValid(RaceTime::normalize('1475'))); // 75秒
        $this->assertFalse(RaceTime::isValid(RaceTime::normalize('12')));
        $this->assertFalse(RaceTime::isValid(RaceTime::normalize('はやい')));
    }

    public function test_seconds_for_sorting(): void
    {
        $this->assertSame(870.0, RaceTime::toSeconds('1430'));
        $this->assertSame(245.3, RaceTime::toSeconds("4'05\"30"));
    }
}
