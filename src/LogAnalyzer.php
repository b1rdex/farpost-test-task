<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use RuntimeException;

final class LogAnalyzer
{
    /**
     * @var callable|null
     */
    private $onLogParseError;

    /**
     * @param resource $stream          поток данных из access-лог'а
     * @param float    $slaAvailability минимально допустимый уровень доступности (проценты. Например, "99.9")
     * @param int      $slaResponseTime приемлемое время ответа (миллисекунды. Например, "45")
     * @param int      $samplePeriod    период семплирования интервалов, в секундах. По-умолчанию 5 секунд
     *                                  (сколько секунд должно пройти с последней failure, чтобы считать что интервал завершён)
     *
     * @return \App\Period[]
     */
    public function analyze($stream, float $slaAvailability, int $slaResponseTime, ?int $samplePeriod = null): array
    {
        $samplePeriod = $samplePeriod ?? 5;
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
            if (
                $firstFailAt !== null && $lastProcessedAt !== null
                && $at->getTimestamp() > $lastProcessedAt->getTimestamp() + $samplePeriod
            ) {
                $period = new Period($firstFailAt, $lastProcessedAt, $succeeded, $failed);
                if ($period->availability() < $slaAvailability) {
                    $result[] = $period;
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

        if ($firstFailAt !== null && $lastProcessedAt !== null) {
            $period = new Period($firstFailAt, $lastProcessedAt, $succeeded, $failed);
            if ($period->availability() < $slaAvailability) {
                $result[] = $period;
            }
        }

        return $result;
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
        if (!($at instanceof DateTimeImmutable)) {
            throw new RuntimeException('Date parse failed – ' . $matches['at']);
        }

        return ['at' => $at, 'status' => (int)$matches['status'], 'time' => (float)$matches['time']];
    }

    /**
     * @param callable|null $onLogParseError
     *
     * @psalm-param callable(\Throwable):void $onLogParseError
     */
    public function setOnLogParseError(?callable $onLogParseError): void
    {
        $this->onLogParseError = $onLogParseError;
    }
}
