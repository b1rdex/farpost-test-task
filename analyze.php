<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\LogAnalyzer;

$analyzer = new LogAnalyzer();

$stream = STDIN;
$availability = 99.9;
$responseTime = 40;
$samplePeriod = 5;

echo 'Max response time: ' . $responseTime . '. Min availability: ' . $availability . '. Sample period: ' . $samplePeriod . PHP_EOL;

$result = $analyzer->analyse($stream, $availability, $responseTime);
var_dump($result);
