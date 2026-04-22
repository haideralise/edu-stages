<?php
// version 2.0.7, update 22-06-2025
// version 1.2.0, update 18-04-2025
namespace App\Services;

use App\Services\DateDayWeekService;

class ClassesServiceCommon
{
    /**
     * JsonService instance
     */
    private $jsonService;

    /**
     * ArrayServiceCommon instance
     */
    private $arrayServiceCommon;

    /**
     * DateDayWeekService instance
     */
    private $dateDayWeekService;

    public function __construct()
    {
        $this->jsonService = new JsonService();
        $this->arrayServiceCommon = new ArrayServiceCommon();
        $this->dateDayWeekService = new DateDayWeekService();
    }

    function classes_sort($classes)
    {
        if (empty($classes))
        {
            return array();
        }
        switch (date('w'))
        {
            case 0:
                $week = '日';
                break;
            case 6:
                $week = '六';
                break;
            case 5:
                $week = '五';
                break;
            case 4:
                $week = '四';
                break;
            case 3:
                $week = '三';
                break;
            case 2:
                $week = '二';
                break;
            case 1:
                $week = '一';
                break;
        }
        $today    = date('w');
        $weekdays = ['日', '一', '二', '三', '四', '五', '六'];
        $weekdays = array_merge(
            array_slice($weekdays, $today),
            array_slice($weekdays, 0, $today)
        );
        $times = array();
        foreach ($classes as $key => $value)
        {
            $time = $this->dateDayWeekService->get_time_24h($value['class_name']);
            $pos  = strpos($time, '-');
            if ($pos)
            {
                $time = substr($time, 0, $pos);
            }
            $time                  = str_replace(':', '', $time);
            $times[]               = $time;
            $classes[$key]['time'] = $time;
        }
        $times = array_unique($times);
        sort($times);
        $now_time   = date('Hi');
        $time_index = 0;
        foreach ($times as $key => $value)
        {
            if ($now_time >= $value)
            {
                $time_index = $key;
            }
        }
        $times = array_merge(array_slice($times, $time_index), array_slice($times, 0, $time_index));

        foreach ($classes as $key => $value)
        {
            $_class_name = $value['class_name'];
            $_pos        = strpos($_class_name, '星期');
            $_class_name = substr($_class_name, $_pos);
            $_class_week = mb_substr($_class_name, 2, 1);
            // 如果包含當前日期則排序0, 反之, 則按照規則排序
            if (strpos($_class_name, $week, 0) !== false)
            {
                $value['sort1'] = 0;
            }
            else
            {
                $value['sort1'] = array_search($_class_week, $weekdays);
            }
            $value['sort2'] = array_search($value['time'], $times);

            if (isset($value['student']) && !is_array($value['student']))
            {
                $value['student'] = $this->jsonService->decode_json($value['student']);
            }
            if (isset($value['student_transfer']) && !is_array($value['student_transfer']))
            {
                $value['student_transfer'] = $this->jsonService->decode_json($value['student_transfer']);
            }

            if (empty($value['student']) && empty($value['student_transfer']))
            {
                $value['sort1'] = 10;
            }

            $classes[$key] = $value;
        }

        $classes = $this->arrayServiceCommon->arrlist_sort($classes, array('sort1' => 1, 'sort2' => 1));

        return $classes;
    }

    public function sortClassesByDayAndTime(array $classes): array
    {
        // Get current day of week (0=Sunday, 6=Saturday)
        $currentDay = date('w');
        $currentTime = date('H:i');

        // Chinese weekday names in order (Sunday to Saturday)
        $weekdayNames = ['日', '一', '二', '三', '四', '五', '六'];

        // Extract day and time from class names
        foreach ($classes as &$class) {
            $className = $class['class_name'];

            // Find the weekday in the class name
            $dayIndex = null;
            foreach ($weekdayNames as $index => $dayName) {
                if (strpos($className, '星期' . $dayName) !== false) {
                    $dayIndex = $index;
                    break;
                }
            }

            // Find the time in the class name (assuming format like "7:00pm")
            preg_match('/(\d{1,2}:\d{2})(am|pm)/i', $className, $timeMatches);
            $time = !empty($timeMatches) ? $timeMatches[1] . $timeMatches[2] : '12:00am';
            $time24 = date('H:i', strtotime($time));

            $class['_sort_day'] = $dayIndex;
            $class['_sort_time'] = $time24;
        }
        unset($class);

        // Custom sorting
        usort($classes, function($a, $b) use ($currentDay, $currentTime) {
            // Compare days relative to today
            $aDayDiff = ($a['_sort_day'] - $currentDay + 7) % 7;
            $bDayDiff = ($b['_sort_day'] - $currentDay + 7) % 7;

            // Same day - sort by time (future times first, then past times)
            if ($aDayDiff === $bDayDiff) {
                if ($aDayDiff === 0) {
                    // For today's classes, future times come first
                    if ($a['_sort_time'] >= $currentTime && $b['_sort_time'] < $currentTime) {
                        return -1;
                    }
                    if ($a['_sort_time'] < $currentTime && $b['_sort_time'] >= $currentTime) {
                        return 1;
                    }
                }
                // Then sort by time ascending
                return strcmp($a['_sort_time'], $b['_sort_time']);
            }

            // Different days - sort by day difference
            return $aDayDiff - $bDayDiff;
        });

        return $classes;
    }
}
