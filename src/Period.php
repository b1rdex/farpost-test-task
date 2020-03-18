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

namespace App;

use DateTimeImmutable;

final class Period
{
    private DateTimeImmutable $start;

    private DateTimeImmutable $end;

    private int $succeeded;

    private int $failed;

    public function __construct(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $succeeded,
        int $failed
    ) {
        $this->start = $start;
        $this->end = $end;
        $this->succeeded = $succeeded;
        $this->failed = $failed;
    }

    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function availability(): float
    {
        return \round($this->succeeded / ($this->succeeded + $this->failed) * 100, 1);
    }
}
