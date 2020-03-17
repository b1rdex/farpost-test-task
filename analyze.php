<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\LogAnalyzer;

// ошибки чтения лог файла пишем в stderr
$onLogParseError = static function (Throwable $throwable): void {
    if ($throwable->getMessage() !== 'Empty log line') {
        fwrite(STDERR, $throwable->__toString() . \PHP_EOL);
    }
};
$analyzer = new LogAnalyzer($onLogParseError);

$stream = STDIN;
$availability = 99.9;
$responseTime = 40;
$samplePeriod = 5;

echo 'Max response time: ' . $responseTime . '. Min availability: ' . $availability . '. Sample period: ' . $samplePeriod . PHP_EOL;

$result = $analyzer->analyze($stream, $availability, $responseTime);
var_dump($result);
