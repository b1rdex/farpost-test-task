<?php

declare(strict_types=1);

/**
 * Copyright (c) 2020 Anatoly Pashin
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/b1rdex/farpost-test-task
 */

namespace Test\Unit;

use App\LogAnalyzer;
use App\Period;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @covers \App\LogAnalyzer
 *
 * @uses   \App\Period
 */
final class LogAnalyzerTest extends TestCase
{
    /**
     * @dataProvider provider
     *
     * @param string $log
     * @param array  $expected
     * @param float  $slaAvailability
     * @param int    $slaResponseTime
     * @param ?int   $samplePeriod
     */
    public function testItShouldWork(string $log, array $expected, float $slaAvailability, int $slaResponseTime, ?int $samplePeriod = null): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream($log);
        $result = $sut->analyze($stream, $slaAvailability, $slaResponseTime, $samplePeriod);
        $result = \array_map(static function (Period $period): array {
            return [
                'period start' => $period->getStart()->format('c'),
                'period end' => $period->getEnd()->format('c'),
                'succeeded count' => $period->getSucceeded(),
                'failed count' => $period->getFailed(),
                'availability' => $period->availability(),
            ];
        }, $result);
        self::assertEquals($expected, $result);
    }

    public function testItShouldThrowOnLogParse(): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream('zzz');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown log format/');
        $sut->analyze($stream, .0, 0);
    }

    public function testItShouldThrowOnDateParse(): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream('192.168.32.181 - - [14/Jun/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 23.251219 "-" "@list-item-updater" prio:0');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Date parse failed/');
        $sut->analyze($stream, .0, 0);
    }

    /**
     * @return \Generator<string, array{log: string, expected: array, slaAvailability: float, slaResponseTime: int}>
     */
    public function provider(): iterable
    {
        yield 'пустой лог файл' => [
            'log' => '',
            'expected' => [],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 1,
        ];

        yield 'лог файл из пустых строк' => [
            'log' => "\n \n \n",
            'expected' => [],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 1,
        ];

        yield 'нет проблемных периодов' => [
            'log' => <<<'LOG'
                192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 23.251219 "-" "@list-item-updater" prio:0
                192.168.32.181 - - [14/06/2017:16:47:03 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 200 2 44.510983 "-" "@list-item-updater" prio:0
            LOG,
            'expected' => [],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 99,
        ];

        yield 'одна запись и она проблемная' => [
            'log' => <<<'LOG'
                192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 500 2 23.251219 "-" "@list-item-updater" prio:0
            LOG,
            'expected' => [
                [
                    'period start' => '2017-06-14T16:47:02+10:00',
                    'period end' => '2017-06-14T16:47:02+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
            ],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 99,
        ];

        yield 'одна запись и она проблемная по времени' => [
            'log' => <<<'LOG'
                192.168.32.181 - - [14/06/2017:16:47:12 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 1123.251219 "-" "@list-item-updater" prio:0
            LOG,
            'expected' => [
                [
                    'period start' => '2017-06-14T16:47:12+10:00',
                    'period end' => '2017-06-14T16:47:12+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
            ],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 1,
        ];

        yield '2/3 проблемных, но доступность соблюдена' => [
            'log' => <<<'LOG'
                192.168.32.181 - - [14/06/2017:16:47:01 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 500 2 23.251219 "-" "@list-item-updater" prio:0
                192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 23.251219 "-" "@list-item-updater" prio:0
                192.168.32.181 - - [14/06/2017:16:47:03 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 500 2 23.251219 "-" "@list-item-updater" prio:0
            LOG,
            'expected' => [],
            'slaAvailability' => 30.,
            'slaResponseTime' => 99,
        ];

        $bigLog = <<<'LOG'
            192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 200 2 44.510983 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:47:03 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 23.251219 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:47:04 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 200 2 30.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:47:10 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 200 2 30.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:48:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 500 2 30.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:48:03 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 504 2 30.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:48:22 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 503 2 30.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:54:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 200 2 90.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:54:03 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 200 2 20.164372 "-" "@list-item-updater" prio:0
            192.168.32.181 - - [14/06/2017:16:54:04 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=e356713 HTTP/1.1" 200 2 90.164372 "-" "@list-item-updater" prio:0
        LOG;

        yield 'большой лог' => [
            'log' => $bigLog,
            'expected' => [
                [
                    'period start' => '2017-06-14T16:47:02+10:00',
                    'period end' => '2017-06-14T16:47:04+10:00',
                    'succeeded count' => 1,
                    'failed count' => 2,
                    'availability' => 33.3,
                ],
                [
                    'period start' => '2017-06-14T16:47:10+10:00',
                    'period end' => '2017-06-14T16:47:10+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:48:02+10:00',
                    'period end' => '2017-06-14T16:48:03+10:00',
                    'succeeded count' => 0,
                    'failed count' => 2,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:48:22+10:00',
                    'period end' => '2017-06-14T16:48:22+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:54:02+10:00',
                    'period end' => '2017-06-14T16:54:04+10:00',
                    'succeeded count' => 1,
                    'failed count' => 2,
                    'availability' => 33.3,
                ],
            ],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 30,
        ];

        yield 'большой лог, большой sample period' => [
            'log' => $bigLog,
            'expected' => [
                [
                    'period start' => '2017-06-14T16:47:02+10:00',
                    'period end' => '2017-06-14T16:48:22+10:00',
                    'succeeded count' => 3,
                    'failed count' => 4,
                    'availability' => 42.9,
                ],
                [
                    'period start' => '2017-06-14T16:54:02+10:00',
                    'period end' => '2017-06-14T16:54:04+10:00',
                    'succeeded count' => 1,
                    'failed count' => 2,
                    'availability' => 33.3,
                ],
            ],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 31,
            'samplePeriod' => 60,
        ];
    }

    /**
     * @param string $log
     *
     * @return resource
     */
    private function createStream(string $log)
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert(\is_resource($stream));
        \fwrite($stream, $log);
        \rewind($stream);

        return $stream;
    }
}
