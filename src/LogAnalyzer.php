<?php

declare(strict_types=1);

namespace App;

use DateTime;
use DateTimeImmutable;
use RuntimeException;
use function fgets;

class LogAnalyzer
{
    /**
     * @param resource $stream          поток данных из access-лог'а
     * @param float    $slaAvailability минимально допустимый уровень доступности (проценты. Например, "99.9")
     * @param int      $slaResponseTime приемлемое время ответа (миллисекунды. Например, "45")
     * @param int      $samplePeriod    период семплирования интервалов, в секундах
     *                                  (сколько секунд должно пройти с последней failure, чтобы считать что интервал завершён)
     *
     * @return string[]
     */
    public function analyse($stream, float $slaAvailability, int $slaResponseTime, int $samplePeriod = 5): array
    {
        $result = [];

        $firstFailAt = null;
        $lastFailAt = null;
        $failed = 0;
        $succeed = 0;

        while (false !== ($line = fgets($stream))) {
            try {
                $parsed = $this->parseLine($line);
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() !== 'Empty log line') {
                    fwrite(STDERR, $exception->__toString() . \PHP_EOL);
                }
                continue;
            }

            [$at, $status, $time] = [$parsed['at'], $parsed['status'], $parsed['time']];
            assert($at instanceof DateTimeImmutable);
            assert(\is_int($status));
            assert(\is_float($time));

            // 5xx или большое время ответа
            if (($status >= 500 && $status <= 599) || $time >= $slaResponseTime) {
                $lastFailAt = $at;
                if ($firstFailAt === null) {
                    $firstFailAt = $at;
                }
                $failed++;
            } else {
                if ($firstFailAt === null) {
                    continue;
                }
                $succeed++;

                // если с последней проблемы прошло больше sample period, то обрабатываем проблемный период
                if ($at->getTimestamp() > $lastFailAt->getTimestamp() + $samplePeriod) {
                    $availability = $failed / ($succeed + $failed);
                    if ($availability < $slaAvailability) {
                        $result[] = [
                            'first problem' => $firstFailAt,
                            'last problem' => $lastFailAt,
                            'now' => $at,
                            'succeed' => $succeed,
                            'failed' => $failed,
                            'availability' => $availability,
                        ];
                    }
                    $firstFailAt = $lastFailAt = null;
                    $failed = $succeed = 0;
                }
            }
        }

        if (false && $firstFailAt) {
            $availability = $failed / ($succeed + $failed);
            if ($availability < $slaAvailability) {
                $result[] = [
                    'first problem' => $firstFailAt,
                    'last problem' => $lastFailAt,
                    'now' => $at,
                    'succeed' => $succeed,
                    'failed' => $failed,
                    'availability' => $availability,
                ];
            }
        }

        // return \array_map(static function (array $item) {
        //     return \array_merge($item, ['first problem' => date('H:i:s', $item['first problem'])]);
        // }, $result);
        return $result;
    }

    /**
     * @psalm-return null|array{at: DateTimeImmutable, status: int, time: float}
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (!$line) {
            throw new RuntimeException('Empty log line');
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
