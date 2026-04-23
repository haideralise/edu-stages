<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EduClassUser;
use App\Models\WpUser;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EduAttendanceController extends Controller
{
    use ApiResponse;

    private const CLASS_OPTIONS = [
        '幼兒游泳班',
        '兒童游泳班',
        '成人游泳班',
        '暑期幼兒游泳班',
        '暑期兒童游泳班',
        '成人四式改良班',
        '女子成人游泳班',
    ];

    private const PAGE_SIZE = 20;

    // -------------------------------------------------------------------------
    // GET /edu/attendance — page render
    // Mirrors AttendanceController::attendance() Admin path
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $tStart = microtime(true);

        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $districtId = (int)$request->get('district_id', 0);
        $districtId2 = (int)$request->get('district_id2', 0);
        $lv3 = $request->get('lv3', '');
        $classId = (int)$request->get('class_id', 0);
        $monthYm = $request->get('m', date('Y-m')); // JS uses ?m=

        if ($isAdmin) {
            $filters = [
                'district_id' => $districtId,
                'district_id2' => $districtId2,
                'lv3' => $lv3,
                'month' => $monthYm,
            ];
        } else {
            $coachClassIds = auth()->user()?->getCoachClassIds() ?? [];
            $filters = [
                'class_ids' => $coachClassIds,
                'month' => $monthYm,
            ];
        }

        // ClassMonthFacade::fetchMonthlyClasses (10eng §1)
        $data = app(\App\Services\ClassMonthFacade::class)
            ->fetchMonthlyClasses($filters, ['with_attendance_level' => 'summary']);
        $classesAll = array_values($data['classes_all'] ?? []);

        // Auto-select first class if none in query string
        if (!$classId && !empty($classesAll)) {
            $classId = (int)$classesAll[0]['class_id'];
        }

        // Find selected class + its class_user row
        $class = null;
        $classUser = null;
        foreach ($classesAll as $c) {
            if ((int)($c['class_id'] ?? 0) === $classId) {
                $class = $c;
                $classUser = $c; // classes_all rows contain class_user data
                break;
            }
        }

        // classes_select for dropdown — all classes in result
        $classesSelect = array_map(fn($c) => [
            'class_id' => $c['class_id'],
            'class_name' => $c['class_name'] ?? '',
        ], $classesAll);

        // classes_lv3 — deduplicated by lv3
        $classes_lv3 = collect($data['classes_lv3'] ?? [])->unique('lv3')->values()->toArray();

        // Month list for selected class (for month dropdown in attendance_admin view)
        $months = [];
        if ($classId) {
            $months = EduClassUser::where('class_id', $classId)
                ->orderBy('sort', 'desc')
                ->get(['month', 'class_year'])
                ->map(fn($cu) => ['month' => $cu->getRawOriginal('month'), 'class_year' => $cu->class_year])
                ->toArray();
        }

        // District lists for district-menu component
        $district = [];
        $district2 = [];
        foreach ($classesAll as $c) {
            $did = $c['district_id'] ?? null;
            if ($did && !isset($district[$did])) {
                $district[$did] = $c['district_name'] ?? $did;
            }
        }

        // Prev/next month for month-selector
        $preNextMonth = app(\App\Services\ClassService::class)->getPreAndNextMonth();

        $pageLoadMs = null;
        if ($isAdmin && auth()->user()?->user_login === 'mssc') {
            $pageLoadMs = round((microtime(true) - $tStart) * 1000, 2);
        }

        $view = $isAdmin
            ? 'edu.attendance.attendance_admin'
            : 'edu.attendance.attendance_coach';

        return view($view, [
            'option_arr' => self::CLASS_OPTIONS,
            'months' => $months,
            'preNextMonth' => $preNextMonth,
            'district_id' => $districtId,
            'district_id2' => $districtId2,
            'lv3' => $lv3,
            'district' => $district,
            'district2' => $district2,
            'classes_lv3' => $classes_lv3,
            'class' => $class,
            'classes' => $classesAll,
            'classes_select' => $classesSelect,
            'is_admin' => $isAdmin,
            'class_id' => $classId,
            'class_user' => $classUser,
            'month' => $monthYm,
            'pageLoadMs' => $pageLoadMs,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /edu/admin/attendance/ajax — AJAX handler
    // Mirrors AttendanceService::handleAjax()
    // Handles: attendance, delete, get_list, get_page
    // -------------------------------------------------------------------------

    public function ajaxUpdate(Request $request): JsonResponse
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $handle = $request->input('handle');
        $classId = (int)($request->input('class_id') ?: $request->query('class_id', 0));
        $date = $request->input('date', date('Y-m-d'));
        $month = $request->input('month', '');
        $classYear = $classId ? (string)substr($date, 0, 4) : date('Y');

        // ── handle=attendance — mark a student's attendance ──────────────────
        if ($handle === 'attendance' && $isAdmin) {
            $request->validate([
                'user_id' => ['required', 'integer', 'min:1'],
                'date' => ['required', 'date_format:Y-m-d'],
                'month' => ['required', 'string'],
                'attendance' => ['required', 'string', 'in:present,late,absent,clear'],
            ]);

            $userId = (int)$request->input('user_id');
            $attendance = $request->input('attendance');

            if ($attendance === 'clear') {
                DB::table('edu_attendance')
                    ->where('class_id', $classId)
                    ->where('user_id', $userId)
                    ->where('date', $date)
                    ->delete();
            } else {
                DB::table('edu_attendance')->updateOrInsert(
                    ['class_id' => $classId, 'user_id' => $userId, 'date' => $date],
                    ['month' => $month, 'attendance' => $attendance, 'class_year' => $classYear]
                );
            }

            return response()->json(['data' => null, 'message' => 'Success']);
        }

        // ── handle=delete — delete all attendance for a date ─────────────────
        if ($handle === 'delete' && $isAdmin) {
                $request->validate([
                    'date' => ['required', 'date_format:Y-m-d'],
                    'class_id' => ['required', 'integer', 'min:1'],
                ]);

                DB::table('edu_attendance')
                    ->where('class_id', $classId)
                    ->where('date', $date)
                    ->delete();

                return response()->json(['data' => null, 'message' => 'Success']);
            }

            // ── handle=get_page — return total page count ─────────────────────────
            if ($handle === 'get_page') {
                $classUser = EduClassUser::where('class_id', $classId)
                    ->where('month', $month)
                    ->where('class_year', $classYear)
                    ->first();

                $studentIds = $classUser ? ($classUser->student ?? []) : [];
                $teacherIds = $classUser ? ($classUser->teacher ?? []) : [];
                $transferIds = $classUser ? ($classUser->student_transfer ?? []) : [];
                $allIds = array_values(array_unique(array_merge($studentIds, $teacherIds, $transferIds)));

                $pageAll = (int)ceil(count($allIds) / self::PAGE_SIZE);
                return response()->json(['data' => ['pages' => $pageAll], 'message' => '成功']);
            }

            // ── handle=get_list — load student list with attendance for a date ────
            // Uses AttendanceSummaryService::attachMonthSummaryToMixedUsers (10eng §4)
            if ($handle === 'get_list') {
                $page = max(1, (int)$request->input('page', 1));

                $classUser = EduClassUser::where('class_id', $classId)
                    ->where('month', $month)
                    ->where('class_year', $classYear)
                    ->first();

                $studentIds = $classUser ? ($classUser->student ?? []) : [];
                $teacherIds = $classUser ? ($classUser->teacher ?? []) : [];
                $transferIds = $classUser ? ($classUser->student_transfer ?? []) : [];
                $allIds = array_values(array_unique(array_merge($studentIds, $teacherIds, $transferIds)));

                // Paginate
                $pageIds = array_slice($allIds, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

                if (empty($pageIds)) {
                    return response()->json(['data' => ['list' => []], 'message' => '成功']);
                }

                // Load user display data + role styling (mirrors edu2 handleAjax)
                $wpUsers = WpUser::whereIn('ID', $pageIds)->with('meta')->get()->keyBy('ID');

                $classUsers = [];
                foreach ($pageIds as $uid) {
                    $user = $wpUsers[$uid] ?? null;
                    if (!$user) {
                        continue;
                    }

                    $meta = $user->meta->keyBy('meta_key');

                    if (in_array($uid, $teacherIds)) {
                        $index = 1;
                        $style = 'style="background:#eee"';
                    } elseif (in_array($uid, $transferIds)) {
                        $index = 3;
                        $style = 'style="background:#fffde7"';
                    } else {
                        $index = 2;
                        $style = '';
                    }

                    $classUsers[] = [
                        'ID' => $uid,
                        'first_name' => $meta['billing_first_name']->meta_value ?? ($meta['first_name']->meta_value ?? ''),
                        'last_name' => $meta['billing_last_name']->meta_value ?? ($meta['last_name']->meta_value ?? ''),
                        'billing_phone' => $meta['billing_phone']->meta_value ?? '',
                        'billing_gender' => $meta['billing_gender']->meta_value ?? '',
                        'user_email' => $user->user_email,
                        'index' => $index,
                        'style' => $style,
                    ];
                }

                // Sort: teachers first, then students, then transfers
                usort($classUsers, fn($a, $b) => $a['index'] <=> $b['index']);

                // Batch attendance summary + postday via AttendanceSummaryService (10eng §4)
                app(\App\Services\AttendanceSummaryService::class)
                    ->attachMonthSummaryToMixedUsers($classUsers, $classId, $month, $classYear, $date);
                // Writes attendance (summary) and postday into each $classUsers entry in-place

                return response()->json(['data' => ['list' => $classUsers], 'message' => '成功']);
            }

            return response()->json(['message' => 'Unknown handle', 'code' => 'INVALID_HANDLE'], 400);
        }

        // -------------------------------------------------------------------------
        // POST /edu/admin/attendance/delete — delete all attendance for a date
        // -------------------------------------------------------------------------

        public function ajaxDelete(Request $request): JsonResponse
        {
            $request->validate([
                'class_id' => ['required', 'integer', 'min:1'],
                'date' => ['required', 'date_format:Y-m-d'],
            ]);

            DB::table('edu_attendance')
                ->where('class_id', (int)$request->input('class_id'))
                ->where('date', $request->input('date'))
                ->delete();

            return $this->success(['message' => 'Success']);
        }
    }
