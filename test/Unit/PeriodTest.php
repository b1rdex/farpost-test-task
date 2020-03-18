<?php

declare(strict_types=1);

namespace Test\Unit;

use App\Period;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \App\Period
 */
final class PeriodTest extends TestCase
{
    /**
     * @test
     * @dataProvider provider
     */
    public function it_should_compute_availability(int $succeeded, int $failed, float $expected): void
    {
        $sut = new Period(new DateTimeImmutable(), new DateTimeImmutable(), $succeeded, $failed);
        static::assertSame($expected, $sut->availability());
    }

    /**
     * @psalm-return \Generator<int,array{succeeded: int, failed: int, availability: float}>
     */
    public function provider(): iterable
    {
        yield ['succeeded' => 1, 'failed' =>  1, 'availability' => 50.];
        yield ['succeeded' => 1, 'failed' =>  2, 'availability' => 33.3];
        yield ['succeeded' => 2, 'failed' =>  1, 'availability' => 66.7];
        yield ['succeeded' => 99, 'failed' =>  1, 'availability' => 99.];
        yield ['succeeded' => 999, 'failed' =>  1, 'availability' => 99.9];
        yield ['succeeded' => 9999, 'failed' =>  1, 'availability' => 100.];
    }
}
