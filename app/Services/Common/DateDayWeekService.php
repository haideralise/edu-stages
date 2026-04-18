<?php
// version 2.0.7, update 22-06-2025
// version 2.0.6, update 21-06-2025
// version 2.0.3, update 13-06-2025
// version 1.7.0, update 28-04-2025
// version 1.6.0, update 27-04-2025
// version 1.4.0, update 22-04-2025
// version 1.3.0, update 21-04-2025
// version 1.2.0, update 18-04-2025
namespace App\Services\Common;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateDayWeekService
{
    public function exclude_other_month_days($days, $month)
    {
        // 處理 null 或空值
        if ($days === null || $days === '') {
            return [];
        }
        
        if (!is_array($days))
        {
            // PHP 8.1+ 兼容：確保不是 null 才調用 explode
            if (is_string($days)) {
                $days = explode(',', $days);
            } else {
                return [];
            }
        }
        
        if (!is_array($month))
        {
            $month = $this->get_array_month($month);
        }
        
        // 如果 month 是空數組，返回空數組
        if (empty($month)) {
            return [];
        }

        foreach ($days as $key => $value)
        {
            // 跳過空值
            if (empty($value)) {
                continue;
            }
            
            // 驗證日期格式
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                unset($days[$key]);
                continue;
            }
            
            $n = date('n', $timestamp);
            if (!in_array($n, $month))
            {
                unset($days[$key]);
            }
        }
        $days = $this->days_sort($days);
        $days = array_unique($days);
        return $days;
    }

    // 获取当月的天
    public function get_array_days($months, $weeks, $year = '')
    {
        // 處理空輸入
        if (empty($months) || empty($weeks)) {
            return [];
        }
        
        if (empty($year))
        {
            $year = date('Y');
        }
        
        if (!is_array($months))
        {
            // 驗證月份是否有效
            if (empty($months) || !is_numeric($months)) {
                return [];
            }
            $months = array($months);
        }
        
        if (!is_array($weeks))
        {
            // 驗證星期是否有效
            if (empty($weeks) || !is_numeric($weeks)) {
                return [];
            }
            $weeks = array($weeks);
        }
        
        // 如果數組為空，返回空數組
        if (empty($months) || empty($weeks)) {
            return [];
        }
        
        $dates = array();
        foreach ($months as $month)
        {
            // 驗證月份範圍
            if (empty($month) || !is_numeric($month) || $month < 1 || $month > 12) {
                continue;
            }
            
            $day_count = $this->get_days_in_month($month, $year);
            for ($day = 1; $day <= $day_count; $day++)
            {
                $day_week = date('N', strtotime("$year-$month-$day"));
                if (in_array($day_week, $weeks))
                {
                    $dates[] = date('Y-m-d', strtotime("$year-$month-$day"));
                }
            }
        }
        return $dates;
    }

    public function get_array_month($input)
    {
        // 處理 null 或空輸入
        if (empty($input) || (!is_string($input) && !is_numeric($input))) {
            return [];
        }
        
        // 確保輸入是字符串
        $input = (string)$input;
        
        preg_match_all('/(\d{1,2})月/', $input, $matches);
        
        // 如果沒有匹配結果，返回空數組
        if (empty($matches[1])) {
            return [];
        }
        
        $rt = $matches[1];
        return array_unique($rt);
    }

    /**
     * 检查订单月份是否与班级月份匹配
     * 支持单月（如"9月"）和跨月格式（如"9月-10月"）
     * 统一替代 isMonthMatchedForClassMonth 和 isMonthMatchedLocal
     * 
     * @param string $order_month 订单月份
     * @param string $class_month 班级月份
     * @return bool 是否匹配
     */
    public function isMonthMatched($order_month, $class_month)
    {
        if (empty($order_month) || empty($class_month)) {
            return false;
        }
        
        // 精确匹配
        if ($order_month === $class_month) {
            return true;
        }
        
        // 解析订单月份和班级月份为数组
        $order_months = $this->get_array_month($order_month);
        $class_months = $this->get_array_month($class_month);
        
        // 检查是否有交集
        $intersection = array_intersect($order_months, $class_months);
        return !empty($intersection);
    }

    /**
     * 解析月份范围为数组，并返回年份映射
     * 支持跨年处理（如"12月-1月"）
     * 
     * @param string $month_range 月份范围（如"9月"或"11月-12月"或"12月-1月"）
     * @param int $year 基准年份
     * @return array ['months' => [...], 'year_map' => [...]]
     */
    public function parseMonthRangeWithYear($month_range, $year)
    {
        $months = [];
        $year_map = [];
        
        if (strpos($month_range, '-') !== false) {
            list($start_text, $end_text) = explode('-', $month_range);
            $start_num = intval(str_replace('月', '', trim($start_text)));
            $end_num = intval(str_replace('月', '', trim($end_text)));
            
            if ($end_num < $start_num) {
                // 跨年，如 12月-1月
                for ($m = $start_num; $m <= 12; $m++) {
                    $months[] = $m . '月';
                    $year_map[$m . '月'] = $year - 1;
                }
                for ($m = 1; $m <= $end_num; $m++) {
                    $months[] = $m . '月';
                    $year_map[$m . '月'] = $year;
                }
            } else {
                // 同年，如 11月-12月
                for ($m = $start_num; $m <= $end_num; $m++) {
                    $months[] = $m . '月';
                    $year_map[$m . '月'] = $year;
                }
            }
        } else {
            // 单月，如 9月
            $month_text = trim($month_range);
            $months = [$month_text];
            $year_map[$month_text] = $year;
        }
        
        return ['months' => $months, 'year_map' => $year_map];
    }

    public function get_array_week($input)
    {
        // 處理 null 或空輸入
        if (empty($input) || !is_string($input)) {
            return [];
        }
        
        // 執行正則匹配
        $matched = preg_match('/星期([一二三四五六天日、]+)/u', $input, $matches);
        
        // 如果匹配失敗或沒有捕獲組，返回空數組
        if (!$matched || !isset($matches[1])) {
            return [];
        }
        
        $week = $matches[1];
        
        // 處理 explode 的 null 輸入（PHP 8.1+ 兼容）
        if (empty($week)) {
            return [];
        }
        
        $week = explode('、', $week);
        $week_mapping = array(
            '一' => 1,
            '二' => 2,
            '三' => 3,
            '四' => 4,
            '五' => 5,
            '六' => 6,
            '日' => 7,
            '天' => 7,
        );
        $week_numbers = array();
        foreach ($week as $day)
        {
            if (isset($week_mapping[$day]))
            {
                $week_numbers[] = $week_mapping[$day];
            }
        }
        return array_unique($week_numbers);
    }

    public function days_sort($days = array())
    {
        if (empty($days))
        {
            return array();
        }
        foreach ($days as $key => $value)
        {
            $days[$key] = trim($value);
        }
        $days = array_unique($days);
        foreach ($days as $key => &$value)
        {
            if (!empty($value))
            {
                $value = strtotime($value);
            }
            else
            {
                unset($days[$key]);
            }
        }
        sort($days);
        foreach ($days as $key => &$value)
        {
            $value = date('Y-m-d', $value);
        }
        return $days;
    }

    public function get_time_24h($text)
    {
        $pattern = '/(\d{1,2}:\d{2}(?:am|pm)-\d{1,2}:\d{2}(?:am|pm))/';
        preg_match($pattern, $text, $matches);
        if (empty($matches[0]))
        {
            return '';
        }
        $time              = $matches[0];
        list($start, $end) = explode('-', $time);
        $converted_start   = $this->convert_time_to_24h($start);
        $converted_end     = $this->convert_time_to_24h($end);
        $converted_times   = $converted_start.'-'.$converted_end;
        return $converted_times;
    }

    public function is_current_month($month, $now_month = '')
    {
        // 更簡潔的寫法
        if (empty($month)) {
            return false;
        }
        
        if (empty($now_month))
        {
            $now_month = time();
        }
        if (!is_numeric($now_month))
        {
            $now_month = strtotime($now_month);
        }
        if (strlen($now_month) > 2)
        {
            $now_month = date('n', $now_month);
        }

        $month = str_replace('月', '', $month);
        $month = explode('-', $month);
        if (in_array($now_month, $month))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Return the timestamp of the first *future* occurrence of $dateTimeStr
     * that still lies inside the *current* calendar month.
     * – $dateTimeStr examples:
     *     「星期五|7:00pm-8:00pm」
     *     「星期三、五|4:00pm-5:00pm」
     * – $now  : reference timestamp (defaults to `time()`)
     * – false : if the class has no more runs this month
     */
    function next_occurrence_this_month(string $dateTimeStr): int|false
    {
        $now          = time();                                     // 11:25 am 4 Jul 2025 in your example
        $currentMonth   = date('Y-m', $now);                          // "2025-07"
        $todayStart     = strtotime(date('Y-m-d 00:00:00', $now));
        $todayDow       = (int)date('N', $now);                       // 1–7 (Mon–Sun)

        // 1) split the original "day|time" chunk
        [$weekdayPart, $timePart] = explode('|', $dateTimeStr, 2);
        [$startTimeStr]           = explode('-', $timePart, 2);       // only the *start* time matters

        // convert "4:00pm" → "16:00"
        $startTime24 = date('H:i', strtotime($startTimeStr));

        // 2) support multiple weekdays: 「星期三、五」or「星期三,五」
        // $weekdayPart = str_replace(['、', '，'], ',', $weekdayPart);    // unify separators
        // $weekdays    = array_map('trim', explode(',', $weekdayPart));

        $weekdays = $this->extract_weekdays($weekdayPart);

        $candidates = [];

        foreach ($weekdays as $wd) {
            if (!$this->chinese_day_to_weekday_index($wd)) {
                // unknown label → skip
                continue;
            }
            $targetDow = $this->chinese_day_to_weekday_index($wd);

            // number of days we need to add to reach that weekday (0–6)
            $delta = ($targetDow - $todayDow + 7) % 7;

            // build YYYY‑MM‑DD start‑time on that weekday
            $candidateDate = $todayStart + $delta * 86400;
            $candidateTs   = strtotime(date('Y-m-d', $candidateDate) . " $startTime24:00");

            // if it's *today* and we've already passed the start time, jump one week ahead
            if ($delta === 0 && $candidateTs <= $now) {
                $candidateTs += 7 * 86400;
            }

            // keep only if candidate still falls inside the *same* month as $now
            if (date('Y-m', $candidateTs) === $currentMonth) {
                $candidates[] = $candidateTs;
            }
        }

        if (!$candidates) {
            // everything left this month has finished
            return false;
        }

        return min($candidates);   // earliest upcoming occurrence
    }

    // function extract_weekdays(string $weekdayPart): array
    // {
    //     preg_match_all('/星期[一二三四五六日天]/u', $weekdayPart, $matches);
    //     return $matches[0];  // e.g. ['星期五'], or ['星期三', '星期五']
    // }

    function extract_weekdays(string $weekdayPart): array
    {
        // Replace all separators with a comma
        $weekdayPart = str_replace(['、', '，', ' ', '|'], ',', $weekdayPart);

        // Extract only the part that includes "星期" and the days
        $matches = [];
        preg_match('/星期([一二三四五六日天,]+)/u', $weekdayPart, $matches);
        
        if (!isset($matches[1])) {
            return [];
        }

        // Split the matched days string
        $dayChars = explode(',', $matches[1]);

        // Reattach '星期' to each
        $result = [];
        foreach ($dayChars as $char) {
            $char = trim($char);
            if ($char !== '') {
                $result[] = '星期' . $char;
            }
        }

        return $result;
    }

    private function chinese_day_to_weekday_index(string $chinese)
    {
        // $map = [
        //     '星期日' => 0,
        //     '星期一' => 1,
        //     '星期二' => 2,
        //     '星期三' => 3,
        //     '星期四' => 4,
        //     '星期五' => 5,
        //     '星期六' => 6,
        // ];
        $map = [
            '星期一' => 1,
            '星期二' => 2,
            '星期三' => 3,
            '星期四' => 4,
            '星期五' => 5,
            '星期六' => 6,
            '星期日' => 7,
            '星期天' => 7,
        ];

        return $map[$chinese] ?? null;
    }


    public function convert_time_to_24h($time)
    {
        $date_time = \DateTime::createFromFormat('g:ia', $time);
        return $date_time->format('H:i');
    }

    public function get_days_in_month($month, $year)
    {
        return date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    public function get_next_year_month($class_year, $month)
    {
        $rt          = array();
        $next_month  = $this->get_string_next_month($month);
        $rt['month'] = $next_month;
        $next_month  = $this->get_array_month($next_month);
        $month1      = isset($next_month[0]) ? $next_month[0] : 0;
        $month2      = isset($next_month[1]) ? $next_month[1] : 0;
        if ($month1 == 1 || $month2 == 1)
        {
            $class_year = $class_year + 1;
        }
        $rt['class_year'] = $class_year;
        return $rt;
    }

    public function is_current_year($year = '', $now_year = '')
    {
        if (empty($year))
        {
            return true;
        }
        if (empty($now_year))
        {
            $now_year = date('Y');
        }
        return $year == $now_year;
    }

    /**
     * Calendar year/month used for renew_current (e.g. {@see StudentPaymentServiceCommon::displayOrders}):
     * days 1–15 map to the previous calendar month; day 16+ uses the actual month.
     *
     * Uses {@see Carbon} so tests can fix time via {@see Carbon::setTestNow()}.
     *
     * @return array{year: int, month: int}
     */
    public function getRenewDisplaySystemYearMonth(?CarbonInterface $at = null): array
    {
        $dt = $at !== null ? Carbon::parse($at) : Carbon::now();

        $_system_year = (int) $dt->year;
        $_system_month = (int) $dt->month;
        if ($dt->day < 16) {
            $_system_month--;
            if ($_system_month === 0) {
                $_system_month = 12;
                $_system_year--;
            }
        }

        return [
            'year' => $_system_year,
            'month' => $_system_month,
        ];
    }

    public function get_string_next_month($input)
    {
        $month_mapping = [
            '1月'  => 1,
            '2月'  => 2,
            '3月'  => 3,
            '4月'  => 4,
            '5月'  => 5,
            '6月'  => 6,
            '7月'  => 7,
            '8月'  => 8,
            '9月'  => 9,
            '10月' => 10,
            '11月' => 11,
            '12月' => 12,
        ];
        $reverse_mapping = array_flip($month_mapping);
        $months          = preg_split('/[-、]/', $input);
        $next_months     = [];
        foreach ($months as $month)
        {
            if (isset($month_mapping[$month]))
            {
                $current_month = $month_mapping[$month];
                $next_month    = ($current_month % 12) + 1;
                $next_months[] = $next_month;
            }
        }
        if (count($next_months) > 1)
        {
            // 兩個月的需要再加一次月份, 單月不用
            $startMonth = ($next_months[0] % 12) + 1;
            $endMonth   = ($next_months[1] % 12) + 1;
            return $reverse_mapping[$startMonth].'-'.$reverse_mapping[$endMonth];
        }
        else
        {
            return $reverse_mapping[$next_months[0]];
        }
    }

    public function date_to_month_days($days)
    {
        if (!is_array($days)) {
            $days = explode(',', $days);
        }
        $out_array = array();
        foreach ($days as $day) {
            if (empty($day)) continue;
            $stamp = strtotime($day);
            $m = date('m', $stamp);
            $d = date('d', $stamp);
            $out_array[$m][] = $d;
        }
        $out_string = '';
        foreach ($out_array as $m => $days) {
            $out_string .= '#' . $m . '月: ';
            $_temp = '';
            foreach ($days as $d) {
                $_temp .= $d . ', ';
            }
            $_temp = substr($_temp, 0, -2);
            $out_string .= $_temp . ' ';
        }
        return $out_string;
    }

    function get_string_prev_month($input)
    {
        $month_mapping = [
            '1月'  => 1,
            '2月'  => 2,
            '3月'  => 3,
            '4月'  => 4,
            '5月'  => 5,
            '6月'  => 6,
            '7月'  => 7,
            '8月'  => 8,
            '9月'  => 9,
            '10月' => 10,
            '11月' => 11,
            '12月' => 12,
        ];
        $reverse_mapping = array_flip($month_mapping);
        $months          = preg_split('/[-、]/', $input);
        $prev_months     = [];
        foreach ($months as $month)
        {
            if (isset($month_mapping[$month]))
            {
                $prev_months[] = $this->get_prev_month($month_mapping[$month]);
            }
        }
        if (count($prev_months) > 1)
        {
            // 兩個月的需要再加一次月份, 單月不用
            $startMonth = $this->get_prev_month($prev_months[0]);
            $endMonth   = $this->get_prev_month($prev_months[1]);
            return $reverse_mapping[$startMonth].'-'.$reverse_mapping[$endMonth];
        }
        else
        {
            return $reverse_mapping[$prev_months[0]];
        }
    }

    private function get_prev_month($month)
    {
        $month_prev = $month == 1 ? 12 : ($month - 1);
        return $month_prev;
    }

    function get_days($input_week, $input_month, $input_year = '')
    {
        $name         = $input_week.$input_month;
        $days         = $this->get_array_class_name_days($name, $input_year);
        $_date_output = '';
        foreach ($days as $key => $value)
        {
            $_date_output .= $value.', ';
        }
        $_date_output = substr($_date_output, 0, -2);
        return $_date_output;
    }

    function get_array_class_name_days($class_name, $year = '')
    {
        $week  = $this->get_array_week($class_name);
        $month = $this->get_array_month($class_name);
        $days  = $this->get_array_days($month, $week, $year);
        return $days;
    }

    public function time_range($timeRange)
    {
        // 清理輸入：移除中文字符和其他非時間字符，只保留時間格式（如 8:30am-9:30pm）
        if (empty($timeRange) || !is_string($timeRange)) {
            return '';
        }
        
        // 使用正則表達式提取時間部分：匹配格式如 "8:30am-9:30pm"
        // 格式：\d{1,2}:\d{2}(am|pm)-\d{1,2}:\d{2}(am|pm)
        if (preg_match('/(\d{1,2}:\d{2}(?:am|pm))\s*-\s*(\d{1,2}:\d{2}(?:am|pm))/i', $timeRange, $matches)) {
            $startTime = trim($matches[1]);
            $endTime = trim($matches[2]);
        } else {
            // 如果正則匹配失敗，嘗試直接分割（兼容舊格式）
            $parts = explode('-', $timeRange);
            if (count($parts) < 2) {
                error_log("[time_range] 無法解析時間字符串格式: {$timeRange}");
                return '';
            }
            
            // 清理每個部分，移除中文字符和非時間字符
            $startTime = preg_replace('/[^\d:amp]/i', '', trim($parts[0]));
            $endTime = preg_replace('/[^\d:amp]/i', '', trim($parts[1]));
        }
        
        // 驗證時間格式
        if (empty($startTime) || empty($endTime)) {
            error_log("[time_range] 時間字符串為空: startTime={$startTime}, endTime={$endTime}, original={$timeRange}");
            return '';
        }
        
        try {
            $start = new \DateTime($startTime);
            $end = new \DateTime($endTime);
        } catch (\Exception $e) {
            // 如果解析失敗，記錄錯誤並返回空字符串
            error_log("[time_range] 無法解析時間字符串: startTime={$startTime}, endTime={$endTime}, original={$timeRange}, error=" . $e->getMessage());
            return '';
        }
        
        $now = new \DateTime();
        if ($end <= new \DateTime('12:00pm')) {
            return 'morning';
        } elseif ($start >= new \DateTime('1:00pm') && $end <= new \DateTime('6:00pm')) {
            return 'afternoon';
        } elseif ($start >= new \DateTime('6:00pm')) {
            return 'evening';
        } else {
            return '';
        }
    }

    public function get_prev_next_month($ym = '')
    {
        if (empty($ym))
        {
            $ym_timestamp = time();
        }
        else
        {
            $ym           = $ym.'-01';
            $ym_timestamp = strtotime($ym);
        }
        $rt = array();
        for ($i = -3; $i < 4; $i++)
        {
            $rt[] = date('Y-m', strtotime($i.' month', $ym_timestamp));
        }
        return $rt;
    }

    public function calculate_age($birthday, $ymd = null)
    {
        if (empty($birthday))
        {
            return 0;
        }
        if (!$ymd)
        {
            $ymd = date('Y-m-d');
        }
        $birthday_date = new \DateTime($birthday);
        $ymd_date      = new \DateTime($ymd);
        $age           = $ymd_date->format('Y') - $birthday_date->format('Y');
        // 校正年龄，如果指定日期还没有到达出生年月日
        if ($ymd_date->format('md') < $birthday_date->format('md'))
        {
            $age--;
        }
        return $age;
    }

    function year_month_sort($year, $month)
    {
        $month  = str_replace('月', '', $month);
        $_m     = explode('-', $month);
        $month0 = isset($_m[0]) ? $_m[0] : '00';
        $month1 = isset($_m[1]) ? $_m[1] : '00';
        $month0 = strlen($month0) == 1 ? '0'.$month0 : $month0;
        $month1 = strlen($month1) == 1 ? '0'.$month1 : $month1;
        $sort   = intval($year.$month0.$month1);
        return $sort;
    }

    function month_num_string($num)
    {
        $num = str_replace(array('月', ' '), '', $num);
        $num = explode('-', $num);
        $rt  = '';
        foreach ($num as $key => $value)
        {
            $rt .= $value.'月-';
        }
        $rt = substr($rt, 0, '-1');
        return $rt;
    }
    
    function get_class_year($order_date, $class_month)
    {
        if (!is_numeric($order_date)) {
            $order_date = strtotime($order_date);
        }
        $order_year = date('Y', $order_date);
        $order_month = date('n', $order_date);
        $class_month_arr = $this->get_array_month($class_month);
        $class_month_val = $class_month_arr[0];
        if ($order_month > 7 && $class_month_val < 5) {
            $order_year++;
        }
        return $order_year;
    }

    public function isWeekend($date) {
        $dayOfWeek = date('N', strtotime($date));
        return ($dayOfWeek >= 6); // 6=Saturday, 7=Sunday
    }
}
