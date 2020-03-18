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

require __DIR__ . '/vendor/autoload.php';

use App\LogAnalyzer;

$analyzer = new LogAnalyzer();

// ошибки чтения лог файла пишем в stderr
$onLogParseError = static function (Throwable $throwable): void {
    if ('Empty log line' !== $throwable->getMessage()) {
        \fwrite(\STDERR, $throwable->__toString() . \PHP_EOL);
    }
};
$analyzer->setOnLogParseError($onLogParseError);

$options = \getopt('u:t:s::v') ?: [];
$availability = (float) ($options['u'] ?? 0);
$responseTime = (int) ($options['t'] ?? 0);
$samplePeriod = (int) ($options['s'] ?? 5);
$isVerbose = isset($options['v']);

if (1 > $availability || 1 > $responseTime || 1 > $samplePeriod) {
    echo <<<'TXT'
    Анализатор access log-а
    =======================
    Читает данные лог файла из stdin и анализирует периоды отказов
    
    Обязательные параметры:
        -u  минимально допустимый уровень доступности (проценты. Например, "99.9")
        -t  приемлемое время ответа (миллисекунды. Например, "45")
    
    Отказом считается запрос завершившийся с любым 500-м кодом возврата (5xx)
    или обрабатываемый дольше чем указанное приемлемое время ответа.
    
    Необязательные параметры:
        -s  Период семплирования интервала (секунды, по-умолчанию 5) – сколько секунд должно пройти
            с последнего отказа, чтобы считать что период отказов завершён
        -v  Включает расширенный вывод
    
    На выходе программа предоставляет временные интервалы, в которые доля отказов системы
    превышала указанную границу, а также уровень доступности в этот интервал времени.
    
    Пример использования программы:
        $ cat access.log | php analyze.php -u 99.9 -t 45
        13:32:26 13:33:15 94.5
        15:23:02 15:23:08 99.8
    TXT . \PHP_EOL;

    exit(2);
}

$stream = \STDIN;

if ($isVerbose) {
    echo 'Parameters: Max response time: ' . $responseTime
        . '. Min availability: ' . $availability
        . '. Sample period: ' . $samplePeriod . \PHP_EOL . \PHP_EOL;
}

$result = $analyzer->analyze($stream, $availability, $responseTime);

foreach ($result as $period) {
    if ($isVerbose) {
        echo $period->getStart()->format('c')
            . ' ' . $period->getEnd()->format('c')
            . ' succeeded ' . $period->getSucceeded()
            . ' / failed ' . $period->getFailed()
            . ' (' . $period->availability() . '%)'
            . \PHP_EOL;
    } else {
        echo $period->getStart()->format('H:i:s')
            . ' ' . $period->getEnd()->format('H:i:s')
            . ' ' . $period->availability()
            . \PHP_EOL;
    }
}
