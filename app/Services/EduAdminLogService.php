<?php

namespace App\Services;

use App\Models\EduAdminLog;
use App\Models\EduLevel;
use App\Models\WpUser;

class EduAdminLogService
{
    public function getLogs(): array
    {
        $rawLogs = EduAdminLog::with(['admin', 'result.eduClass'])
            ->orderByDesc('created')
            ->get();

        $results = $rawLogs->pluck('result')->filter()->keyBy('id');
        $adminIds = $rawLogs->pluck('admin_user_id')->filter()->unique()->values();
        $levelIds = $results->pluck('exam_id')->filter()->unique()->values();
        $studentIds = $results->pluck('user_id')->filter()->unique()->values();

        $levels = EduLevel::whereIn('id', $levelIds)->get()->keyBy('id');
        $students = WpUser::whereIn('ID', $studentIds)->get()->keyBy('ID');
        $admins = WpUser::whereIn('ID', $adminIds)->get()->keyBy('ID');

        return $rawLogs->map(function (EduAdminLog $log) use ($admins, $levels, $students): array {
            $result = $log->result;
            $admin = $admins->get($log->admin_user_id);
            $level = $result ? $levels->get($result->exam_id) : null;
            $student = $result ? $students->get($result->user_id) : null;

            return [
                'id' => $log->id,
                'admin_name' => $admin?->display_name ?? '—',
                'handle' => $log->handle,
                'exam_link' => $result
                    ? url('/edu/admin-log').'?class_id='.$result->class_id.'&class_month='.$result->class_month.'&exam_date='.$result->exam_date
                    : '#',
                'exam_label' => $result
                    ? (($result->eduClass?->class_name ?? '').' '.($result->class_month ?? ''))
                    : '—',
                'exam_date' => $result?->exam_date ?? '—',
                'level_name' => $level?->name ?? '—',
                'student_name' => $student?->display_name ?? '—',
                'before' => $log->before,
                'after' => $log->after,
                'created' => $log->created ? date('Y-m-d H:i:s', $log->created) : '—',
            ];
        })->toArray();
    }
}
