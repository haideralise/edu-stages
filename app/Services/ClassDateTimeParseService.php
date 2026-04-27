<?php

namespace App\Services;

/**
 * wp_3x_edu_class.date_time format parsing.
 * Examples: 逢星期六|11:00am-12:00pm、逢星期二、五|9:00am-10:00am
 * Used for class sorting, entrance-fee timeslot, etc.
 *
 * Ported from edu2/services/Common/ClassDateTimeParseService.php (no DB — no N+1).
 *
 * @version 1.0.0
 */
class ClassDateTimeParseService
{
    private const WEEKDAY_MAP = ['一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '日' => 7];

    /**
     * Extract weekday numbers from date_time (no sort semantics).
     * 逢星期六 → [6]; 逢星期二、五 → [2, 5]
     *
     * @param  mixed  $dateTime  e.g. "逢星期六|11:00am-12:00pm"
     * @return list<int> 1–7 = Mon–Sun; empty if unparseable
     */
    public static function parseWeekdaysFromDateTime($dateTime): array
    {
        if (empty($dateTime) || ! is_string($dateTime)) {
            return [];
        }
        $part = (strpos($dateTime, '|') !== false) ? trim(explode('|', $dateTime)[0]) : $dateTime;
        if ($part === '') {
            return [];
        }
        $weekdays = [];
        if (preg_match_all('/星期([一二三四五六日])/u', $part, $m)) {
            foreach ($m[1] as $c) {
                $w = self::WEEKDAY_MAP[$c] ?? null;
                if ($w !== null && ! in_array($w, $weekdays)) {
                    $weekdays[] = $w;
                }
            }
        }
        if (preg_match_all('/、([一二三四五六日])/u', $part, $m)) {
            foreach ($m[1] as $c) {
                $w = self::WEEKDAY_MAP[$c] ?? null;
                if ($w !== null && ! in_array($w, $weekdays)) {
                    $weekdays[] = $w;
                }
            }
        }

        return array_values(array_unique($weekdays));
    }

    /**
     * Extract start time string from date_time.
     * 逢星期六|11:00am-12:00pm → "11:00am"
     *
     * @param  mixed  $dateTime
     */
    public static function parseStartTimeFromDateTime($dateTime): string
    {
        if (empty($dateTime) || ! is_string($dateTime)) {
            return '';
        }
        $pos = strpos($dateTime, '|');
        $timePart = ($pos !== false) ? trim(substr($dateTime, $pos + 1)) : $dateTime;
        if ($timePart === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}:\d{2}(?:am|pm))/i', $timePart, $m)) {
            return trim($m[1]);
        }
        $parts = explode('-', $timePart);

        return $parts ? trim($parts[0]) : '';
    }
}
