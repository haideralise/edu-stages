<?php

namespace App\ValueObjects;

use Carbon\Carbon;

class EduMonthWindow
{
    public function __construct(
        public readonly int $start,
        public readonly int $end,
    ) {
        //
    }

    public static function single(int $month): self
    {
        return new self($month, $month);
    }

    public static function between(int $start, ?int $end = null): self
    {
        $end = $start + 1;

        if ($end > 12) {
            $end = 1;
        }

        return new self($start, $end);
    }

    public static function fromLegacyString(string $value): self
    {
        $tokens = explode(
            '-',
            preg_replace('/(\d)月/', '$1', $value)
        );

        if (count($tokens) > 1) {
            return new self((int) $tokens[0], (int) $tokens[1]);
        }

        return static::single((int) $tokens[0]);
    }

    public function getStartDateForYear(int $year): Carbon
    {
        return Carbon::create($year, $this->start);
    }

    public function getEndDateForYear(int $year): Carbon
    {
        $endDate = Carbon::create($year, $this->end)
            ->endOfMonth();

        if ($endDate->isBefore($this->getStartDateForYear($year))) {
            $endDate->addYear();
        }

        return $endDate;
    }

    public function isSingleMonth(): bool
    {
        return $this->start === $this->end;
    }

    public function toLegacyString(): string
    {
        if ($this->isSingleMonth()) {
            return "{$this->start}月";
        }

        return "{$this->start}月-{$this->end}月";
    }
}
