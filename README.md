# Farpost.ru test task

[![Integrate](https://github.com/b1rdex/farpost-test-task/workflows/Integrate/badge.svg?branch=master)](https://github.com/b1rdex/farpost-test-task/actions)
[![Prune](https://github.com/b1rdex/farpost-test-task/workflows/Prune/badge.svg?branch=master)](https://github.com/b1rdex/farpost-test-task/actions)
[![Release](https://github.com/b1rdex/farpost-test-task/workflows/Release/badge.svg?branch=master)](https://github.com/b1rdex/farpost-test-task/actions)
[![Renew](https://github.com/b1rdex/farpost-test-task/workflows/Renew/badge.svg?branch=master)](https://github.com/b1rdex/farpost-test-task/actions)

[![Code Coverage](https://codecov.io/gh/b1rdex/farpost-test-task/branch/master/graph/badge.svg)](https://codecov.io/gh/b1rdex/farpost-test-task)
[![Type Coverage](https://shepherd.dev/github/b1rdex/farpost-test-task/coverage.svg)](https://shepherd.dev/github/b1rdex/farpost-test-task)

## Анализатор access log-а

Читает данные лог файла из stdin и анализирует периоды отказов

Обязательные параметры:
```
    -u  минимально допустимый уровень доступности (проценты. Например, "99.9")
    -t  приемлемое время ответа (миллисекунды. Например, "45")
```
Отказом считается запрос завершившийся с любым 500-м кодом возврата (5xx)
или обрабатываемый дольше чем указанное приемлемое время ответа.

Необязательные параметры:
```
    -s  Период семплирования интервала (секунды, по-умолчанию 5) – сколько секунд должно пройти
        с последнего отказа, чтобы считать что период отказов завершён
    -v  Включает расширенный вывод
```
На выходе программа предоставляет временные интервалы, в которые доля отказов системы
превышала указанную границу, а также уровень доступности в этот интервал времени.

Пример использования программы:
```
$ cat access.log | php analyze.php -u 99.9 -t 45
13:32:26 13:33:15 94.5
15:23:02 15:23:08 99.8
```

### Использование через docker
Собираем контейнер и запускаем.

:bulb: Важный момент – необходимо указать флаг `-i` для docker run, чтобы передача данных через пайп работала.
```
docker build -t log-analyzer .
cat log.log | docker run --rm -i log-analyzer -u 99.9 -t 45
```
