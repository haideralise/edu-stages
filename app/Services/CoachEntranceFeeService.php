<?php

namespace App\Services;

use App\Services\DistrictManagementService;

/**
 * Coach entrance fee calculation.
 * Timeslot by start time: &lt;12:00 morning, 12:00–18:00 afternoon, ≥18:00 evening.
 * Dedup: same pool + same date + same timeslot counts once; different pools or timeslots count separately.
 * Rates: weekday $17, weekend $19.
 *
 * Ported from edu2/services/Common/CoachEntranceFeeService.php.
 * {@see DistrictManagementService::extractDistrictFromClassName} for pool name (no per-iteration DB).
 */
class CoachEntranceFeeService
{
    private const WORKDAY_FEE = 17;

    private const WEEKEND_FEE = 19;

    public function __construct(
        private DistrictManagementService $districtManagementService,
    ) {}

    /**
     * @param  string  $startTimeStr  e.g. "11:00am" or "5:00pm"
     * @return string 'morning'|'afternoon'|'evening'|''
     */
    public function getTimeslotFromStartTime($startTimeStr)
    {
        if (empty($startTimeStr) || ! is_string($startTimeStr)) {
            return '';
        }
        $startTimeStr = trim($startTimeStr);
        $ts = strtotime($startTimeStr);
        if ($ts === false) {
            return '';
        }
        $hour = (int) date('G', $ts);
        $minute = (int) date('i', $ts);
        $minutes = $hour * 60 + $minute;

        if ($minutes < 12 * 60) {
            return 'morning';
        }
        if ($minutes < 18 * 60) {
            return 'afternoon';
        }

        return 'evening';
    }

    /**
     * From date_time extract start time, e.g. "班名|11:00am-12:00pm" → "11:00am".
     * Delegates to {@see ClassDateTimeParseService} (same rules as edu2).
     *
     * @param  mixed  $dateTime
     */
    public function parseStartTimeFromDateTime($dateTime): string
    {
        return ClassDateTimeParseService::parseStartTimeFromDateTime($dateTime);
    }

    /**
     * @param  array<int|string, array{class_name?: string, date_time?: string, days2?: array<string, mixed>}>  $classes_with_days
     * @param  string  $date_ym  Target month Y-m
     * @return array{workday_num: int, weekend_num: int, workday_fee: int, weekend_fee: int, attend_fee: int, by_class: array<int|string, array{class_workday_num: int, class_weekend_num: int, class_workday_fee: int, class_weekend_fee: int}>}
     */
    public function calculateEntranceFee(array $classes_with_days, $date_ym): array
    {
        $workday_num = 0;
        $weekend_num = 0;
        /** @var array<string, int|string> */
        $daily_pool_times = [];

        $by_class = [];

        foreach ($classes_with_days as $key => $class_data) {
            $class_name = $class_data['class_name'] ?? '';
            $date_time = $class_data['date_time'] ?? '';
            $days2 = $class_data['days2'] ?? [];

            $district_info = $this->districtManagementService->extractDistrictFromClassName($class_name);
            $pool_location = $district_info['district_name'] ?? '未知';

            $start_time_str = $this->parseStartTimeFromDateTime($date_time);
            $timeslot = $this->getTimeslotFromStartTime($start_time_str);

            $class_workday_num = 0;
            $class_weekend_num = 0;

            foreach ($days2 as $date => $v) {
                $date_timestamp = strtotime((string) $date);
                if ($date_timestamp === false || date('Y-m', $date_timestamp) !== $date_ym) {
                    continue;
                }

                $day_pool_timeslot_key = $date . '_' . $pool_location . '_' . $timeslot;

                if (! isset($daily_pool_times[$day_pool_timeslot_key])) {
                    $daily_pool_times[$day_pool_timeslot_key] = $key;
                    $week = date('w', $date_timestamp);

                    if ($week == 6 || $week == 0) {
                        $weekend_num++;
                        $class_weekend_num++;
                    } else {
                        $workday_num++;
                        $class_workday_num++;
                    }
                }
            }

            $class_workday_fee = self::WORKDAY_FEE * $class_workday_num;
            $class_weekend_fee = self::WEEKEND_FEE * $class_weekend_num;

            $by_class[$key] = [
                'class_workday_num' => $class_workday_num,
                'class_weekend_num' => $class_weekend_num,
                'class_workday_fee' => $class_workday_fee,
                'class_weekend_fee' => $class_weekend_fee,
            ];
        }

        $workday_fee = self::WORKDAY_FEE * $workday_num;
        $weekend_fee = self::WEEKEND_FEE * $weekend_num;
        $attend_fee = $workday_fee + $weekend_fee;

        return [
            'workday_num' => $workday_num,
            'weekend_num' => $weekend_num,
            'workday_fee' => $workday_fee,
            'weekend_fee' => $weekend_fee,
            'attend_fee' => $attend_fee,
            'by_class' => $by_class,
        ];
    }
}
