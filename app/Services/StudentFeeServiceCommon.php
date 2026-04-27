<?php

namespace App\Services;

use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Services\AttendanceQueryService;
use Illuminate\Support\Facades\Cache;

/**
 * Ported from edu2/services/Common/StudentFeeServiceCommon.php (renew price / class days).
 */
class StudentFeeServiceCommon
{
    public function __construct(
        private readonly DateDayWeekService $dateDayWeekService,
        private readonly AttendanceQueryService $attendanceQueryService
    ) {}

    /**
     * @deprecated Use getRenewPrice() instead
     */
    public function get_renew_price2($user_id, $class_name, $pa_month, $class_year = '', $amount = 0, $class_fee = 0): array
    {
        return $this->getRenewPrice($user_id, $class_name, $pa_month, $class_year, $amount, $class_fee);
    }

    /**
     * @param  int|string  $class_year
     * @return array{text: string, renew: string, price: float|string, next_month: string, class_days: array}
     */
    public function getRenewPrice($user_id, $class_name, $pa_month, $class_year = '', $amount = 0, $class_fee = 0): array
    {
        $batch = $this->getRenewPriceBatch((int) $user_id, [[
            'key' => '__single__',
            'class_name' => $class_name,
            'pa_month' => $pa_month,
            'class_year' => $class_year,
            'amount' => $amount,
        ]]);

        return $batch['__single__'] ?? [];
    }

    /**
     * Batch renew-price payloads for display lists (same shape as {@see getRenewPrice} per key).
     *
     * @param  list<array{key: string, class_name: string, pa_month: string, class_year?: mixed, amount?: mixed}>  $items
     * @return array<string, array{text: string, renew: string, price: float|string, next_month: string, class_days: array}>
     */
    public function getRenewPriceBatch(int $user_id, array $items): array
    {
        $results = [];
        if ($items === []) {
            return [];
        }

        $classNames = [];
        foreach ($items as $it) {
            if (isset($it['class_name']) && $it['class_name'] !== '') {
                $classNames[$it['class_name']] = true;
            }
        }
        $classesByName = EduClass::query()
            ->whereIn('class_name', array_keys($classNames))
            ->get()
            ->keyBy('class_name');

        $prepared = [];
        foreach ($items as $it) {
            $key = (string) ($it['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $class_name = (string) ($it['class_name'] ?? '');
            $class = $classesByName[$class_name] ?? null;
            if (! $class) {
                $results[$key] = [];
                continue;
            }
            $pa_month = (string) ($it['pa_month'] ?? '');
            $yearForQuery = ($it['class_year'] === '' || $it['class_year'] === null) ? date('Y') : (string) $it['class_year'];
            $amount = $it['amount'] ?? 0;
            $class_id = (int) $class->class_id;
            $prepared[] = [
                'key' => $key,
                'class_name' => $class_name,
                'class_id' => $class_id,
                'pa_month' => $pa_month,
                'yearForQuery' => $yearForQuery,
                'amount' => $amount,
            ];
        }

        if ($prepared === []) {
            return $results;
        }

        $primaryByTriple = [];
        $cuPrimary = EduClassUser::query()
            ->where(function ($q) use ($prepared) {
                $seen = [];
                foreach ($prepared as $p) {
                    $tk = $p['class_id'] . '|' . $p['pa_month'] . '|' . $p['yearForQuery'];
                    if (isset($seen[$tk])) {
                        continue;
                    }
                    $seen[$tk] = true;
                    $q->orWhere(function ($w) use ($p) {
                        $w->where('class_id', $p['class_id'])
                            ->where('month', $p['pa_month'])
                            ->where('class_year', $p['yearForQuery']);
                    });
                }
            })
            ->get();
        foreach ($cuPrimary as $model) {
            $tk = (int) $model->class_id . '|' . (string) ($model->getRawOriginal('month') ?? $model->month) . '|' . (string) ($model->getRawOriginal('class_year') ?? $model->class_year);
            $primaryByTriple[$tk] = $model;
        }

        $mergedRealByMy = [];
        $monthYearSeen = [];
        foreach ($prepared as $p) {
            $myk = $p['pa_month'] . "\0" . $p['yearForQuery'];
            if (isset($monthYearSeen[$myk])) {
                continue;
            }
            $monthYearSeen[$myk] = true;
            $class_users = EduClassUser::query()
                ->where('month', $p['pa_month'])
                ->where('class_year', $p['yearForQuery'])
                ->where(function ($q) use ($user_id) {
                    $uid = (int) $user_id;
                    $q->whereJsonContains('student', $uid)
                        ->orWhereJsonContains('student_transfer', $uid);
                })
                ->get();
            $legacyRows = [];
            foreach ($class_users as $cu) {
                $legacyRows[] = $this->classUserToLegacyArray($cu);
            }
            $mergedRealByMy[$myk] = $this->attendanceQueryService->mergeUserAttendRealDaysForClassUserRows(
                $user_id,
                $p['pa_month'],
                $p['yearForQuery'],
                $legacyRows
            );
        }

        $planDaysCache = [];
        $planSeen = [];
        foreach ($prepared as $p) {
            $tk = $p['class_id'] . '|' . $p['pa_month'] . '|' . $p['yearForQuery'];
            if (isset($planSeen[$tk])) {
                continue;
            }
            $planSeen[$tk] = true;
            $plan = $this->attendanceQueryService->getUserDays(
                $user_id,
                $p['class_id'],
                $p['pa_month'],
                $p['yearForQuery'],
                false
            );
            $planDaysCache[$tk] = is_array($plan) ? $plan : [];
        }

        $dedupeToKeys = [];
        $representativeByDedupe = [];
        foreach ($prepared as $p) {
            $dk = $p['class_id'] . '|' . $p['pa_month'] . '|' . $p['yearForQuery'] . '|' . (string) $p['amount'];
            $dedupeToKeys[$dk][] = $p['key'];
            if (! isset($representativeByDedupe[$dk])) {
                $representativeByDedupe[$dk] = $p;
            }
        }

        $nextCuByTriple = [];
        if ($representativeByDedupe !== []) {
            $dateSvc = $this->dateDayWeekService;
            $nextModels = EduClassUser::query()
                ->where(function ($q) use ($representativeByDedupe, $dateSvc) {
                    $seen = [];
                    foreach ($representativeByDedupe as $p) {
                        $next = $dateSvc->get_next_year_month((int) $p['yearForQuery'], $p['pa_month']);
                        $ntk = $p['class_id'] . '|' . $next['month'] . '|' . (string) $next['class_year'];
                        if (isset($seen[$ntk])) {
                            continue;
                        }
                        $seen[$ntk] = true;
                        $q->orWhere(function ($w) use ($p, $next) {
                            $w->where('class_id', $p['class_id'])
                                ->where('month', $next['month'])
                                ->where('class_year', (string) $next['class_year']);
                        });
                    }
                })
                ->get();
            foreach ($nextModels as $model) {
                $ntk = (int) $model->class_id . '|' . (string) ($model->getRawOriginal('month') ?? $model->month) . '|' . (string) ($model->getRawOriginal('class_year') ?? $model->class_year);
                $nextCuByTriple[$ntk] = $model;
            }
        }

        foreach ($dedupeToKeys as $dk => $outKeys) {
            $p = $representativeByDedupe[$dk];
            $myk = $p['pa_month'] . "\0" . $p['yearForQuery'];
            $tripleKey = $p['class_id'] . '|' . $p['pa_month'] . '|' . $p['yearForQuery'];
            $renew = $this->buildRenewPriceFromPrepared(
                $user_id,
                $p,
                $primaryByTriple,
                $planDaysCache[$tripleKey] ?? [],
                $mergedRealByMy[$myk] ?? [],
                $nextCuByTriple
            );
            foreach ($outKeys as $ok) {
                $results[$ok] = $renew;
            }
        }

        foreach ($items as $it) {
            $k = (string) ($it['key'] ?? '');
            if ($k !== '' && ! array_key_exists($k, $results)) {
                $results[$k] = [];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, \App\Models\EduClassUser>  $primaryByTriple  key class_id|month|year
     * @param  array<string, \App\Models\EduClassUser>  $nextCuByTriple  key class_id|nextMonth|nextYear
     * @return array{text: string, renew: string, price: float|string, next_month: string, class_days: array}
     */
    private function buildRenewPriceFromPrepared(
        int $user_id,
        array $p,
        array $primaryByTriple,
        array $now_month_attend_days_plan,
        array $now_month_attend_days_real,
        array $nextCuByTriple
    ): array {
        $class_name = $p['class_name'];
        $class_id = $p['class_id'];
        $pa_month = $p['pa_month'];
        $yearForQuery = $p['yearForQuery'];
        $amount = $p['amount'];

        $tripleKey = $class_id . '|' . $pa_month . '|' . $yearForQuery;
        $cuModel = $primaryByTriple[$tripleKey] ?? null;
        $class_user = $cuModel ? $this->classUserToLegacyArray($cuModel) : [];

        $now_month = $pa_month;
        $now_month_array = $this->dateDayWeekService->get_array_month($now_month);
        $now_month_days = $this->attendanceQueryService->getClassUserDaysArray($class_user);
        $now_month_days_count = empty($now_month_days) ? 0 : count($now_month_days);

        $individual_class_fee = $this->calculateClassFee($user_id, $class_id);

        if ($individual_class_fee) {
            $price = $individual_class_fee;
        } else {
            $class_type_fee = null;
            if (strpos($class_name, '兒童游泳班') !== false) {
                $class_type_fee = 140;
            } elseif (strpos($class_name, '幼兒游泳班') !== false) {
                $class_type_fee = 160;
            } elseif (strpos($class_name, '女子成人游泳班') !== false) {
                $class_type_fee = 150;
            } elseif (strpos($class_name, '改良班') !== false) {
                $class_type_fee = 150;
            } elseif (strpos($class_name, '成人游泳班') !== false) {
                $class_type_fee = 145;
            } elseif (strpos($class_name, '泳隊訓練') !== false) {
                $class_type_fee = 180;
            }

            if ($class_type_fee) {
                $price = $class_type_fee;
            } else {
                $price = $now_month_days_count ? number_format($amount / $now_month_days_count, 2) : 0;
            }
        }

        if (! is_array($now_month_attend_days_plan)) {
            $now_month_attend_days_plan = [];
        }

        $now_month_attend_days_plan_count = count($now_month_attend_days_plan);
        $now_month_attend_days_real_count = count($now_month_attend_days_real);

        $missed_lesson_count = $now_month_attend_days_plan_count - $now_month_attend_days_real_count;
        $missed_lesson_days = [];
        $added_lesson_days = [];
        $next_lesson_days = [];

        foreach ($now_month_attend_days_plan as $value) {
            $n = (int) date('n', strtotime($value));
            if (in_array($n, $now_month_array, true) && ! in_array($value, $now_month_attend_days_real, true)) {
                $missed_lesson_days[] = $value;
            }

            if (! in_array($n, $now_month_array, true)) {
                $next_lesson_days[] = $value;
            }
        }

        foreach ($now_month_attend_days_real as $value) {
            $n = (int) date('n', strtotime($value));
            if (in_array($n, $now_month_array, true) && ! in_array($value, $now_month_attend_days_plan, true)) {
                $added_lesson_days[] = $value;
            }
        }

        $next = $this->dateDayWeekService->get_next_year_month((int) $yearForQuery, $now_month);
        $ntk = (int) $class_id . '|' . $next['month'] . '|' . (string) $next['class_year'];
        $nextClassUser = $nextCuByTriple[$ntk] ?? null;

        if ($nextClassUser) {
            $next_month_days = $this->attendanceQueryService->getClassUserDaysArray(
                $this->classUserToLegacyArray($nextClassUser)
            );
        } else {
            $next_month_days = $this->getClassNameDaysArray($class_name, $next['month'], $next['class_year']);
        }

        $next_month_days_count = count($next_month_days);
        $renew = ($next_month_days_count - $missed_lesson_count) * (float) $price;
        $renew = number_format($renew, 2);
        $real_lesson_count = $next_month_days_count - $missed_lesson_count;

        $text = "<p>如想繼續報讀下一期游泳班，請交以下學費以安排學位。如不打算繼續上堂，請回覆不繼續上課，謝謝！</p><p>歡迎繼續參加 {$class_name} <b>{$next['month']}</b> 游泳班</p>";
        $text .= '<p>上堂日期: ';
        $text .= $this->dateDayWeekService->date_to_month_days($next_month_days);
        $text .= "(共 <b>{$next_month_days_count}</b> 堂)</p>";

        if ($missed_lesson_days || $added_lesson_days || $next_lesson_days) {
            $text .= '<p>請假補課: ';
            if (! empty($missed_lesson_days)) {
                $text .= '請假/取消: ';
                $text .= $this->dateDayWeekService->date_to_month_days($missed_lesson_days);
            }

            if (! empty($added_lesson_days)) {
                $text .= '補課: ';
                $text .= $this->dateDayWeekService->date_to_month_days($added_lesson_days);
            }

            if (! empty($next_lesson_days)) {
                $text .= '待上課: ';
                $text .= $this->dateDayWeekService->date_to_month_days($next_lesson_days);
            }

            if ($missed_lesson_count) {
                if ($missed_lesson_count < 0) {
                    $text .= '(共多上 <b>' . abs($missed_lesson_count) . '</b> 堂)';
                } else {
                    $text .= "(共 <b>{$missed_lesson_count}</b> 堂未上)";
                }
            }
            $text .= '</p>';
        }

        $text .= "<p class=\"_rm\">每堂學費: $<b><input class_fee class_id=\"{$class_id}\" value=\"{$price}\"></b></p>";
        if ($missed_lesson_count) {
            if ($missed_lesson_count < 0) {
                $text .= "<p>今期學費: <b>\${$price} x {$real_lesson_count}堂 ({$next_month_days_count}+" . abs($missed_lesson_count) . ')</b></p>';
            } else {
                $text .= "<p>今期學費: <b>\${$price} x {$real_lesson_count}堂 ({$next_month_days_count}-{$missed_lesson_count})</b></p>";
            }
        } else {
            $text .= "<p>今期學費: <b>\${$price} x {$next_month_days_count}堂</b></p>";
        }
        $text .= "<p>= <b>\${$renew}</b></p>";
        $text .= '<button class="form-button">Copy Text</button>';

        return [
            'text' => $text,
            'renew' => $renew,
            'price' => $price,
            'next_month' => $next['month'],
            'class_days' => $next_month_days,
        ];
    }

    public function getClassUserDaysArray(array $class_user): array
    {
        return $this->attendanceQueryService->getClassUserDaysArray($class_user);
    }

    /**
     * @param  int|string  $class_year
     * @return list<string>
     */
    private function getClassNameDaysArray(string $class_name, string $month, $class_year = ''): array
    {
        if ($class_name === '' || $month === '') {
            return [];
        }
        $year = ($class_year === '' || $class_year === null) ? date('Y') : (string) $class_year;
        $class = EduClass::query()->where('class_name', $class_name)->first();
        if (! $class) {
            return [];
        }
        $cu = EduClassUser::query()
            ->where('class_id', $class->class_id)
            ->where('month', $month)
            ->where('class_year', $year)
            ->first();
        if (! $cu) {
            return [];
        }

        return $this->attendanceQueryService->getClassUserDaysArray($this->classUserToLegacyArray($cu));
    }

    private function calculateClassFee(int $user_id, int $class_id): mixed
    {
        $k = "user_class_fee_{$user_id}#{$class_id}";

        return Cache::get($k);
    }

    private function classUserToLegacyArray(EduClassUser $cu): array
    {
        $row = $cu->getAttributes();
        foreach (['student', 'student_makeup', 'student_transfer', 'student_order', 'order_id', 'teacher', 'class_exam'] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = $cu->getRawOriginal($col);
            }
        }

        return $row;
    }
}
