<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ClassService;
use App\Services\ClassStudentListService;
use App\Services\CoachBonusReportService;
use App\Services\Common\ArrayServiceCommon;
use App\Services\Common\AttendanceQueryService;
use App\Services\Common\ClassDateTimeParseService;
use App\Services\Common\CoachBonusCalculationService;
use App\Services\Common\CoachEntranceFeeService;
use App\Services\EduCoachService;
use App\Services\PrivateClassService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Port of edu2 {@see \Edu\Controllers\CoachSalaryController::salary} (Stage 4).
 * DebugClassService → {@see ClassStudentListService}; CoachBonusDebugService → {@see CoachBonusReportService}.
 * N+1: {@see ClassStudentListService::preloadEduClassesFromClassRows} before loops that call buildDaysDistributionText.
 */
class CoachSalaryController extends Controller
{
    public function __construct(
        private readonly ClassStudentListService $classStudentListService,
        private readonly CoachBonusReportService $bonusReportService,
        private readonly CoachBonusCalculationService $bonusCalc,
        private readonly PrivateClassService $privateClassService,
        private readonly CoachEntranceFeeService $entranceFeeService,
        private readonly AttendanceQueryService $attendanceQueryService,
        private readonly ArrayServiceCommon $arrayService,
        private readonly EduCoachService $eduCoachService,
        private readonly ClassService $classService,
    ) {
    }

    public function salary(Request $request)
    {
        $guard = auth('wp');
        $role = $guard->getRole();
        if (! in_array($role, ['admin', 'coach'], true)) {
            abort(403);
        }

        $isAdmin = $role === 'admin';
        $userId = (int) ($guard->user()?->ID ?? 0);

        $_start = microtime(true);

        if ($isAdmin && $request->has('coach_id')) {
            $coach_id = (int) $request->query('coach_id');
        } else {
            $coach_id = $userId;
        }

        $today_day = (int) date('j');
        if ($isAdmin) {
            $ym = trim((string) $request->query('month', date('Y-m')));
        } else {
            $ym = ($today_day <= 15)
                ? date('Y-m', strtotime(date('Y-m') . '-01 -1 month'))
                : date('Y-m');
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }
        $year = (int) substr($ym, 0, 4);
        $month = (int) substr($ym, 5, 2);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('m');
            $ym = sprintf('%04d-%02d', $year, $month);
        }

        $coachesData = $this->eduCoachService->getCoachesData();
        $all_coaches = [];
        if (! empty($coachesData['users'])) {
            foreach ($coachesData['users'] as $u) {
                $all_coaches[] = [
                    'ID' => (int) $u['ID'],
                    'display_name' => $u['display_name'] ?? ('教練 ' . (int) ($u['ID'] ?? 0)),
                ];
            }
        }
        $all_coaches = $this->arrayService->arrlist_multisort($all_coaches, 'display_name', true);

        $coach_name = '未知教練';
        foreach ($all_coaches as $c) {
            if ((int) $c['ID'] === $coach_id) {
                $coach_name = $c['display_name'];
                break;
            }
        }

        $classes_with_salary = [];
        $month_text = $month . '月';
        $hourly_wage = 200;
        $month_summary = [
            'curr_classes' => 0,
            'curr_students' => 0,
            'curr_renewals' => 0,
            'curr_bonus' => 0,
            'curr_entrance_fee' => 0,
            'curr_coach_fee' => 0,
            'prev_classes' => 0,
            'prev_students' => 0,
            'prev_renewals' => 0,
            'prev_bonus' => 0,
            'prev_entrance_fee' => 0,
            'prev_coach_fee' => 0,
            'pct_classes' => 0,
            'pct_students' => 0,
            'pct_renewals' => 0,
            'pct_bonus' => 0,
            'pct_entrance_fee' => 0,
            'pct_coach_fee' => 0,
            'curr_coach_late' => 0,
            'curr_student_late' => 0,
            'prev_coach_late' => 0,
            'prev_student_late' => 0,
            'pct_coach_late' => 0,
            'pct_student_late' => 0,
        ];

        $private_salary = ['items' => [], 'total_fee' => 0, 'students' => []];

        if ($coach_id > 0) {
            try {
                $data = $this->classStudentListService->getClassesByCoachYearMonth($coach_id, $year, $month);

                $current_classes = $data['classes'];
                $student_info = $data['student_info'];
                $student_order_map = $data['student_order_map'];
                $student_renewal_map = $data['student_renewal_map'] ?? [];
                $student_days_map = $data['student_days_map'];
                $student_attendance_map = $data['student_attendance_map'] ?? [];

                $this->classStudentListService->preloadEduClassesFromClassRows($current_classes);

                $bonus_map = $this->bonusReportService->getBonusMap(
                    $current_classes,
                    $student_renewal_map,
                    $student_order_map,
                    $student_days_map,
                    $year,
                    $month,
                    $student_attendance_map
                );

                $class_months = [];
                foreach ($current_classes as $cr) {
                    $class_months[] = [
                        'user_id' => $coach_id,
                        'class_id' => (int) $cr['class_id'],
                        'month' => trim($cr['class_month'] ?? '') ?: $month_text,
                        'year' => $year,
                    ];
                }
                $batchCoachAttends = ! empty($class_months)
                    ? $this->attendanceQueryService->fetchUsersMonthAttendByClassMonths([$coach_id], $class_months, null)
                    : [];

                $hourly_wage = $this->classService->getHourlyWage($coach_id);
                $date_ym = $ym;

                $curr_all_student_ids = [];
                $curr_all_renewal_ids = [];

                foreach ($current_classes as $class_row) {
                    $class_id = (int) $class_row['class_id'];
                    $class_name = $class_row['class_name'] ?? '未知班級';
                    $date_time = $class_row['date_time'] ?? '';
                    $class_month = $class_row['class_month'] ?? '';

                    $class_display = $class_name;
                    if (! empty($date_time) && strpos($date_time, '|') !== false) {
                        $time_part = trim(explode('|', $date_time)[1] ?? '');
                        if (! empty($time_part) && strpos($class_name, '|') === false) {
                            $class_display = $class_name . '|' . $time_part;
                        }
                    }

                    $normal_ids = array_filter(
                        array_map('intval', json_decode($class_row['student'] ?? '[]', true) ?: []),
                        fn ($id) => $id > 0
                    );

                    $renewal_count = 0;
                    $class_bonus_amount = 0;
                    $students_bonus = [];

                    foreach ($normal_ids as $sid) {
                        $curr_all_student_ids[] = $sid;
                        $key = "{$sid}_{$class_id}";
                        $is_renewal = ! empty($student_renewal_map[$key]);
                        if ($is_renewal) {
                            $renewal_count++;
                            $curr_all_renewal_ids[] = $sid;
                        }

                        $bi = $bonus_map[$key] ?? null;
                        $fee = isset($student_order_map[$key]) ? (float) ($student_order_map[$key]['amount'] ?? 0) : 0;
                        if ($is_renewal && $bi && isset($bi['bonus_monthly']) && $bi['bonus_monthly'] > 0) {
                            $class_bonus_amount += round((float) $bi['bonus_monthly'], 2);
                        }

                        $days_distribution = $this->classStudentListService->buildDaysDistributionText(
                            $sid,
                            trim($class_month) ?: $month_text,
                            $student_days_map[$class_id] ?? [],
                            $year,
                            false,
                            $class_id,
                            $student_attendance_map[$class_id] ?? [],
                            $class_row['class_days'] ?? ''
                        );
                        if (! $isAdmin && ! empty($days_distribution)) {
                            $lines = explode("\n", $days_distribution);
                            $filtered = array_filter($lines, function ($line) use ($month_text) {
                                return strpos(trim($line), $month_text . ':') === 0;
                            });
                            $days_distribution = implode("\n", $filtered);
                            if ($days_distribution === '') {
                                $days_distribution = '-';
                            }
                        }
                        $students_bonus[] = [
                            'student_name' => $student_info[$sid] ?? '未知',
                            'days_distribution' => $days_distribution,
                            'fee' => $fee,
                            'bonus_rate' => ($is_renewal && $bi) ? ($bi['bonus_rate'] ?? 0) : null,
                            'bonus_amount' => ($is_renewal && $bi && isset($bi['bonus_monthly'])) ? round((float) $bi['bonus_monthly'], 2) : null,
                            'is_renewal' => $is_renewal,
                        ];
                    }

                    usort($students_bonus, function ($a, $b) {
                        if ($a['is_renewal'] !== $b['is_renewal']) {
                            return $a['is_renewal'] ? -1 : 1;
                        }

                        return strcmp($a['student_name'], $b['student_name']);
                    });

                    $total_students = count($normal_ids);
                    $non_renewal_count = $total_students - $renewal_count;
                    $bonus_rate = $this->bonusCalc->bonusRateByCount($renewal_count);

                    $coach_attends_display = ['class_line' => '', 'attend_lines' => []];
                    $cmonth = trim($class_month) ?: $month_text;
                    $class_line_full = $class_display . '   (' . $cmonth . ')';
                    $coach_attends_display['class_line'] = $class_line_full;

                    $attends_for_display = [];
                    if (! empty($batchCoachAttends)) {
                        $attends = $batchCoachAttends[$coach_id][$class_id][$cmonth] ?? [];
                        foreach ($attends as $d => $st) {
                            if (date('Y-m', strtotime($d)) === $date_ym) {
                                $attends_for_display[$d] = $st;
                            }
                        }
                        $summary = $this->attendanceQueryService->formatAttendSummary($attends_for_display);
                        foreach (['present' => '出席', 'late' => '請假', 'absent' => '取消'] as $key => $label) {
                            $v = trim(strip_tags(str_replace('&nbsp;', ' ', $summary[$key] ?? '')));
                            if ($v !== '') {
                                $coach_attends_display['attend_lines'][] = $label . ':' . $v;
                            }
                        }
                    }

                    $attends = $batchCoachAttends[$coach_id][$class_id][$cmonth] ?? [];
                    $class_num = 0;
                    foreach ($attends as $date => $status) {
                        if (($status ?? '') === 'present' && date('Y-m', strtotime($date)) === $date_ym) {
                            $class_num++;
                        }
                    }
                    $class_fee = $class_num * $hourly_wage;

                    $days2_for_entrance = [];
                    foreach ($attends as $d => $st) {
                        if (($st ?? '') === 'present' && date('Y-m', strtotime($d)) === $date_ym) {
                            $days2_for_entrance[$d] = '';
                        }
                    }

                    $coach_late_count = 0;
                    $coach_total_count = 0;
                    foreach ($attends_for_display as $st) {
                        $coach_total_count++;
                        if ($st === 'late') {
                            $coach_late_count++;
                        }
                    }
                    $coach_late_pct = ($coach_total_count > 0)
                        ? (int) round($coach_late_count / $coach_total_count * 100)
                        : 0;

                    $student_late_total = 0;
                    $student_attend_total = 0;
                    $class_att_map = $student_attendance_map[$class_id] ?? [];
                    $query_month_num = $month;
                    foreach ($normal_ids as $sid) {
                        $sid_late = (int) (($class_att_map[$sid][$query_month_num]['late'] ?? 0));
                        $student_late_total += $sid_late;
                        $student_attend_total += $class_num;
                    }
                    $student_late_pct = ($student_attend_total > 0)
                        ? (int) round($student_late_total / $student_attend_total * 100)
                        : 0;
                    $student_late_per_person = ($total_students > 0)
                        ? round($student_late_total / $total_students, 2)
                        : 0;

                    $classes_with_salary[] = [
                        'class_name' => $class_display,
                        'class_name_raw' => $class_name,
                        'class_id' => $class_id,
                        'month' => $class_month,
                        'date_time' => $date_time,
                        'days2' => $days2_for_entrance,
                        'coach_attends_display' => $coach_attends_display,
                        'class_num' => $class_num,
                        'class_fee' => $class_fee,
                        'total_students' => $total_students,
                        'renewal_count' => $renewal_count,
                        'non_renewal_count' => $non_renewal_count,
                        'bonus_rate' => $bonus_rate,
                        'class_bonus_amount' => round($class_bonus_amount, 2),
                        'bonus_info' => [
                            'students' => $students_bonus,
                        ],
                        'coach_late_count' => $coach_late_count,
                        'coach_late_pct' => $coach_late_pct,
                        'student_late_total' => $student_late_total,
                        'student_late_per_person' => $student_late_per_person,
                        'student_late_pct' => $student_late_pct,
                    ];
                }

                foreach ($classes_with_salary as &$cws) {
                    $dt = $cws['date_time'] ?? '';
                    $weekdays = ClassDateTimeParseService::parseWeekdaysFromDateTime($dt);
                    $cws['_sw'] = ! empty($weekdays) ? min($weekdays) : 99;
                    $startStr = ClassDateTimeParseService::parseStartTimeFromDateTime($dt);
                    $cws['_st'] = ($startStr !== '' && ($ts = strtotime($startStr)) !== false) ? $ts : 0;
                }
                unset($cws);
                usort($classes_with_salary, function ($a, $b) {
                    if ($a['_sw'] !== $b['_sw']) {
                        return $a['_sw'] - $b['_sw'];
                    }

                    return $a['_st'] - $b['_st'];
                });
                foreach ($classes_with_salary as &$cws) {
                    unset($cws['_sw'], $cws['_st']);
                }
                unset($cws);

                $classes_for_entrance = [];
                foreach ($classes_with_salary as $cws) {
                    $classes_for_entrance[] = [
                        'class_name' => $cws['class_name_raw'] ?? $cws['class_name'],
                        'date_time' => $cws['date_time'] ?? '',
                        'days2' => $cws['days2'] ?? [],
                    ];
                }
                $entrance_result = $this->entranceFeeService->calculateEntranceFee($classes_for_entrance, $ym);
                $total_entrance_fee = $entrance_result['attend_fee'] ?? 0;
                $entrance_by_class = $entrance_result['by_class'] ?? [];

                foreach ($classes_with_salary as $idx => &$cws) {
                    $ebc = $entrance_by_class[$idx] ?? [];
                    $cws['entrance_workday_num'] = (int) ($ebc['class_workday_num'] ?? 0);
                    $cws['entrance_weekend_num'] = (int) ($ebc['class_weekend_num'] ?? 0);
                    $cws['entrance_workday_fee'] = (float) ($ebc['class_workday_fee'] ?? 0);
                    $cws['entrance_weekend_fee'] = (float) ($ebc['class_weekend_fee'] ?? 0);
                }
                unset($cws);

                $private_salary = $this->privateClassService->getPrivateClassSalaryForMonth($coach_id, $year, $month);
                $private_total_fee = (float) ($private_salary['total_fee'] ?? 0);

                $month_summary['curr_classes'] = count($classes_with_salary);
                $month_summary['curr_students'] = count(array_unique($curr_all_student_ids));
                $month_summary['curr_renewals'] = count(array_unique($curr_all_renewal_ids));
                $month_summary['curr_bonus'] = array_sum(array_column($classes_with_salary, 'class_bonus_amount'));
                $month_summary['curr_coach_fee'] = array_sum(array_column($classes_with_salary, 'class_fee')) + $month_summary['curr_bonus'] + $total_entrance_fee + $private_total_fee;
                $month_summary['curr_entrance_fee'] = $total_entrance_fee;
                $month_summary['entrance_workday_num'] = $entrance_result['workday_num'] ?? 0;
                $month_summary['entrance_weekend_num'] = $entrance_result['weekend_num'] ?? 0;

                $month_summary['curr_coach_late'] = array_sum(array_column($classes_with_salary, 'coach_late_count'));
                $month_summary['curr_student_late'] = array_sum(array_column($classes_with_salary, 'student_late_total'));
                foreach ($private_salary['students'] as $_psb) {
                    $month_summary['curr_student_late'] += (int) ($_psb['student_late_total'] ?? 0);
                }

                $prev_ym = date('Y-m', strtotime($ym . '-01 -1 month'));
                $prev_year = (int) substr($prev_ym, 0, 4);
                $prev_month = (int) substr($prev_ym, 5, 2);
                $prev_month_text = $prev_month . '月';

                $prev_data = $this->classStudentListService->getClassesByCoachYearMonth($coach_id, $prev_year, $prev_month);
                $prev_classes = $prev_data['classes'] ?? [];

                if (! empty($prev_classes)) {
                    $this->classStudentListService->preloadEduClassesFromClassRows($prev_classes);

                    $prev_renewal_map = $prev_data['student_renewal_map'] ?? [];
                    $prev_bonus_map = $this->bonusReportService->getBonusMap(
                        $prev_classes,
                        $prev_renewal_map,
                        $prev_data['student_order_map'] ?? [],
                        $prev_data['student_days_map'] ?? [],
                        $prev_year,
                        $prev_month,
                        $prev_data['student_attendance_map'] ?? []
                    );

                    $prev_class_months = [];
                    foreach ($prev_classes as $pr) {
                        $prev_class_months[] = [
                            'user_id' => $coach_id,
                            'class_id' => (int) $pr['class_id'],
                            'month' => trim($pr['class_month'] ?? '') ?: $prev_month_text,
                            'year' => $prev_year,
                        ];
                    }
                    $prev_batch_attends = $this->attendanceQueryService->fetchUsersMonthAttendByClassMonths([$coach_id], $prev_class_months, null);

                    $prev_all_student_ids = [];
                    $prev_all_renewal_ids = [];
                    $prev_total_bonus = 0;
                    $prev_total_fee = 0;

                    foreach ($prev_classes as $pr) {
                        $prev_class_id = (int) $pr['class_id'];
                        $prev_normal_ids = array_filter(
                            array_map('intval', json_decode($pr['student'] ?? '[]', true) ?: []),
                            fn ($id) => $id > 0
                        );
                        foreach ($prev_normal_ids as $psid) {
                            $prev_all_student_ids[] = $psid;
                            $pk = "{$psid}_{$prev_class_id}";
                            if (! empty($prev_renewal_map[$pk])) {
                                $prev_all_renewal_ids[] = $psid;
                            }
                            $pbi = $prev_bonus_map[$pk] ?? null;
                            if ($pbi && isset($pbi['bonus_monthly']) && $pbi['bonus_monthly'] > 0) {
                                $prev_total_bonus += (float) $pbi['bonus_monthly'];
                            }
                        }
                        $prev_cmonth = trim($pr['class_month'] ?? '') ?: $prev_month_text;
                        $prev_attends = $prev_batch_attends[$coach_id][$prev_class_id][$prev_cmonth] ?? [];
                        $prev_class_num = 0;
                        $prev_coach_late_count = 0;
                        foreach ($prev_attends as $pdate => $pstatus) {
                            if (date('Y-m', strtotime($pdate)) !== $prev_ym) {
                                continue;
                            }
                            if (($pstatus ?? '') === 'present') {
                                $prev_class_num++;
                            } elseif (($pstatus ?? '') === 'late') {
                                $prev_coach_late_count++;
                            }
                        }
                        $prev_total_fee += $prev_class_num * $hourly_wage;
                        $month_summary['prev_coach_late'] += $prev_coach_late_count;

                        $prev_att_map = $prev_data['student_attendance_map'] ?? [];
                        $prev_month_num = $prev_month;
                        foreach ($prev_normal_ids as $_psid) {
                            $month_summary['prev_student_late'] += (int) (($prev_att_map[$prev_class_id][$_psid][$prev_month_num]['late'] ?? 0));
                        }
                    }

                    $prev_classes_for_entrance = [];
                    foreach ($prev_classes as $pr) {
                        $prev_cm = trim($pr['class_month'] ?? '') ?: $prev_month_text;
                        $prev_att = $prev_batch_attends[$coach_id][(int) $pr['class_id']][$prev_cm] ?? [];
                        $prev_days2 = [];
                        foreach ($prev_att as $pd => $ps) {
                            if (($ps ?? '') === 'present' && date('Y-m', strtotime($pd)) === $prev_ym) {
                                $prev_days2[$pd] = '';
                            }
                        }
                        $prev_classes_for_entrance[] = [
                            'class_name' => $pr['class_name'] ?? '',
                            'date_time' => $pr['date_time'] ?? '',
                            'days2' => $prev_days2,
                        ];
                    }
                    $prev_entrance_result = $this->entranceFeeService->calculateEntranceFee($prev_classes_for_entrance, $prev_ym);
                    $prev_entrance_fee = $prev_entrance_result['attend_fee'] ?? 0;

                    $prev_private_salary = $this->privateClassService->getPrivateClassSalaryForMonth($coach_id, $prev_year, $prev_month);
                    $prev_private_fee = (float) ($prev_private_salary['total_fee'] ?? 0);

                    foreach ($prev_private_salary['students'] as $_ppsb) {
                        $month_summary['prev_student_late'] += (int) ($_ppsb['student_late_total'] ?? 0);
                    }

                    $month_summary['prev_classes'] = count($prev_classes);
                    $month_summary['prev_students'] = count(array_unique($prev_all_student_ids));
                    $month_summary['prev_renewals'] = count(array_unique($prev_all_renewal_ids));
                    $month_summary['prev_bonus'] = $prev_total_bonus;
                    $month_summary['prev_entrance_fee'] = $prev_entrance_fee;
                    $month_summary['prev_coach_fee'] = $prev_total_fee + $month_summary['prev_bonus'] + $prev_entrance_fee + $prev_private_fee;
                }

                $pct = function ($curr, $prev) {
                    if ($prev <= 0) {
                        return $curr > 0 ? 100 : 0;
                    }

                    return (int) round(($curr - $prev) / $prev * 100);
                };
                $month_summary['pct_classes'] = $pct($month_summary['curr_classes'], $month_summary['prev_classes']);
                $month_summary['pct_students'] = $pct($month_summary['curr_students'], $month_summary['prev_students']);
                $month_summary['pct_renewals'] = $pct($month_summary['curr_renewals'], $month_summary['prev_renewals']);
                $month_summary['pct_bonus'] = $pct($month_summary['curr_bonus'], $month_summary['prev_bonus']);
                $month_summary['pct_entrance_fee'] = $pct($month_summary['curr_entrance_fee'] ?? 0, $month_summary['prev_entrance_fee'] ?? 0);
                $month_summary['pct_coach_fee'] = $pct($month_summary['curr_coach_fee'], $month_summary['prev_coach_fee']);
                $month_summary['pct_coach_late'] = $pct($month_summary['curr_coach_late'], $month_summary['prev_coach_late']);
                $month_summary['pct_student_late'] = $pct($month_summary['curr_student_late'], $month_summary['prev_student_late']);
            } catch (Throwable $e) {
                Log::error('[CoachSalaryController] 執行錯誤', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        $_elapsed = microtime(true) - $_start;

        $month_display_rule = '';
        if (! $isAdmin) {
            $month_display_rule = ($today_day <= 15)
                ? ((int) date('m') . '月15日或以前顯示上月')
                : ((int) date('m') . '月16日或以後顯示當月');
        }

        return view('edu.coach.coach_salary', [
            'coach_id' => $coach_id,
            'coach_name' => $coach_name,
            'selected_month' => $ym,
            'year' => $year,
            'month_text' => $month_text,
            'all_coaches' => $all_coaches,
            'classes_with_salary' => $classes_with_salary,
            'private_salary' => $private_salary,
            'hourly_wage' => $hourly_wage,
            'month_summary' => $month_summary,
            'month_display_rule' => $month_display_rule,
            'page_load_seconds' => $_elapsed,
            'is_admin' => $isAdmin,
        ]);
    }
}
