<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use RuntimeException;

class LogAnalyzer
{
    /**
     * @var callable|null
     */
    private $onLogParseError;

    /**
     * @param callable|null $onLogParseError обработчик исключений разбора лога
     *
     * @psalm-param callable(\Throwable):void $onLogParseError
     */
    public function __construct(callable $onLogParseError = null)
    {
        $this->onLogParseError = $onLogParseError;
    }

    /**
     * @param resource $stream поток данных из access-лог'а
     * @param float $slaAvailability минимально допустимый уровень доступности (проценты. Например, "99.9")
     * @param int $slaResponseTime приемлемое время ответа (миллисекунды. Например, "45")
     * @param int $samplePeriod период семплирования интервалов, в секундах
     *                                  (сколько секунд должно пройти с последней failure, чтобы считать что интервал завершён)
     *
     * @return array
     */
    public function analyze($stream, float $slaAvailability, int $slaResponseTime, int $samplePeriod = 5): array
    {
        $result = [];

        $firstFailAt = null;
        $lastProcessedAt = null;
        $failed = 0;
        $succeeded = 0;

        while (false !== ($line = fgets($stream))) {
            try {
                $parsed = $this->parseLine($line);
            } catch (RuntimeException $exception) {
                if ($this->onLogParseError === null) {
                    throw $exception;
                }

                ($this->onLogParseError)($exception);
                continue;
            }
            if ($parsed === null) {
                continue;
            }

            [$at, $status, $time] = [$parsed['at'], $parsed['status'], $parsed['time']];
            assert($at instanceof DateTimeImmutable);
            assert(\is_int($status));
            assert(\is_float($time));

            // если с последней проблемы прошло больше sample period, то обрабатываем проблемный период
            if ($firstFailAt !== null && $lastProcessedAt !== null && $at->getTimestamp() > $lastProcessedAt->getTimestamp() + $samplePeriod) {
                $availability = (float)($succeeded / ($succeeded + $failed) * 100);
                // todo: remove true
                if (true || $availability < $slaAvailability) {
                    $result[] = [
                        'period start' => $firstFailAt,
                        'period end' => $lastProcessedAt,
                        'current time' => $at,
                        'succeeded count' => $succeeded,
                        'failed count' => $failed,
                        'availability' => $availability,
                    ];
                }
                $firstFailAt = null;
                $failed = $succeeded = 0;
            }

            // это нам понадобится чтобы определить конец проблемного периода
            $lastProcessedAt = $at;

            // 5xx или большое время ответа
            if (($status >= 500 && $status <= 599) || $time >= $slaResponseTime) {
                if ($firstFailAt === null) {
                    $firstFailAt = $at;
                }
                $failed++;
            } else {
                if ($firstFailAt === null) {
                    continue;
                }
                $succeeded++;
            }
        }

        if ($firstFailAt) {
            $availability = (float)($succeeded / ($succeeded + $failed) * 100);
            if ($availability < $slaAvailability) {
                $result[] = [
                    'period start' => $firstFailAt,
                    'period end' => $lastProcessedAt,
                    'current time' => $at ?? null,
                    'succeeded count' => $succeeded,
                    'failed count' => $failed,
                    'availability' => $availability,
                ];
            }
        }

        return array_map(static function (array $item): array {
            $item['period start'] = $item['period start']->format('c');
            $item['period end'] = $item['period end']->format('c');
            $item['current time'] = $item['current time']->format('c');

            return $item;
        }, $result);
    }

    /**
     * @psalm-return null|array{at: DateTimeImmutable, status: int, time: float}
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (!$line) {
            return null;
        }

        // 192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 200 2 44.510983 "-" "@list-item-updater" prio:0
        if (
        !\preg_match('/^(?P<host>.*)\s(.*)\s(.*)\s\[(?P<at>.*)]\s"(.*)"\s(?P<status>\d+)\s(.*)\s(?P<time>\d+\.\d+)\s"(.*)"\s"(.*)"\s(.*)$/',
            $line, $matches)
        ) {
            throw new RuntimeException('Unknown log format – ' . $line);
        }

        $at = DateTimeImmutable::createFromFormat('d/m/Y:H:i:s O', $matches['at']);
        if (!$at) {
            throw new RuntimeException('Date parse failed – ' . $matches['at']);
        }

        return ['at' => $at, 'status' => (int)$matches['status'], 'time' => (float)$matches['time']];
    }
}
