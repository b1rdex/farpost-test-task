<?php

declare(strict_types=1);

namespace Test\Unit;

use App\LogAnalyzer;
use PHPUnit\Framework;
use RuntimeException;

/**
 * @internal
 *
 * @covers \App\LogAnalyzer
 */
final class LogAnalyzerTest extends Framework\TestCase
{
    /**
     * @test
     * @dataProvider provider
     */
    public function it_should_work(string $log, array $expected, float $slaAvailability, int $slaResponseTime): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream($log);
        $result = $sut->analyze($stream, $slaAvailability, $slaResponseTime);
        static::assertEquals($expected, $result);
    }

    /**
     * @return resource
     */
    private function createStream(string $log)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $log);
        rewind($stream);

        return $stream;
    }

    /**
     * @test
     */
    public function it_should_throw_on_log_parse(): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream('zzz');
        static::expectException(RuntimeException::class);
        static::expectExceptionMessageMatches('/Unknown log format/');
        $sut->analyze($stream, .0, 0);
    }

    /**
     * @test
     */
    public function it_should_throw_on_date_parse(): void
    {
        $sut = new LogAnalyzer();
        $stream = $this->createStream('192.168.32.181 - - [14/Jun/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=7ae28555 HTTP/1.1" 200 2 23.251219 "-" "@list-item-updater" prio:0');
        static::expectException(RuntimeException::class);
        static::expectExceptionMessageMatches('/Date parse failed/');
        $sut->analyze($stream, .0, 0);
    }

    /**
     * @return \Generator<string, array{slaAvailability: float, slaResponseTime: int, log: string, expected: array}>
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
                    'current time' => '2017-06-14T16:47:02+10:00',
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
                    'current time' => '2017-06-14T16:47:12+10:00',
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

        yield 'большой лог' => [
            'log' => <<<'LOG'
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
            LOG,
            'expected' => [
                [
                    'period start' => '2017-06-14T16:47:02+10:00',
                    'period end' => '2017-06-14T16:47:04+10:00',
                    'current time' => '2017-06-14T16:47:10+10:00',
                    'succeeded count' => 1,
                    'failed count' => 2,
                    'availability' => 1/3*100,
                ],
                [
                    'period start' => '2017-06-14T16:47:10+10:00',
                    'period end' => '2017-06-14T16:47:10+10:00',
                    'current time' => '2017-06-14T16:48:02+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:48:02+10:00',
                    'period end' => '2017-06-14T16:48:03+10:00',
                    'current time' => '2017-06-14T16:48:22+10:00',
                    'succeeded count' => 0,
                    'failed count' => 2,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:48:22+10:00',
                    'period end' => '2017-06-14T16:48:22+10:00',
                    'current time' => '2017-06-14T16:54:02+10:00',
                    'succeeded count' => 0,
                    'failed count' => 1,
                    'availability' => .0,
                ],
                [
                    'period start' => '2017-06-14T16:54:02+10:00',
                    'period end' => '2017-06-14T16:54:04+10:00',
                    'current time' => '2017-06-14T16:54:04+10:00',
                    'succeeded count' => 1,
                    'failed count' => 2,
                    'availability' => 1/3*100,
                ],
            ],
            'slaAvailability' => 99.9,
            'slaResponseTime' => 30,
        ];
    }
}
