<?php

namespace App\Services;

use App\Models\EduClassUser;
use App\Models\EduPrivate;
use App\Models\EduUser;
use App\Models\WpUser;
use Illuminate\Support\Facades\DB;

class EduCoachService
{
    public function getCoachesData(): array
    {
        $coachIds = EduClassUser::query()
            ->whereNotNull('teacher')
            ->get(['teacher'])
            ->flatMap(fn($row) => $row->teacher ?? [])
            ->map(fn($id) => (int)$id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($coachIds)) {
            return ['users' => [], 'hourly_wage' => [], 'all_coaches' => []];
        }

        $usersCollection = WpUser::whereIn('ID', $coachIds)
            ->get(['ID', 'display_name', 'user_login']);

        $users = $usersCollection->map(fn($u) => [
            'ID' => $u->ID,
            'display_name' => $u->display_name,
            'first_name' => $u->display_name,
            'last_name' => '',
            'user_login' => $u->user_login,
        ])->toArray();

        $hourlyWage = EduUser::whereIn('user_id', $coachIds)
            ->get(['user_id', 'hourly_wage'])
            ->keyBy('user_id')
            ->map(fn($u) => [
                'user_id' => $u->user_id,
                'hourly_wage' => $u->hourly_wage ?? 200.00,
            ])
            ->toArray();

        $allCoaches = $usersCollection->map(fn($u) => [
            'ID' => $u->ID,
            'display_name' => $u->display_name,
        ])->sortBy('display_name')
            ->values()
            ->toArray();

        return [
            'users' => $users,
            'hourly_wage' => $hourlyWage,
            'all_coaches' => $allCoaches,
        ];
    }

    public function updateWage(int $userId, float $hourlyWage): array
    {
        EduUser::updateOrCreate(
            ['user_id' => $userId],
            ['hourly_wage' => $hourlyWage]
        );

        return ['status' => true, 'message' => 'Hourly wage updated successfully'];
    }

    public function getPrivateClasses(int $coachId): array
    {
        if ($coachId <= 0) {
            return [];
        }

        return EduPrivate::where('coach_id', $coachId)
            ->orderBy('class_date', 'asc')
            ->orderBy('class_time', 'asc')
            ->get()
            ->toArray();
    }

    public function savePrivateClasses(int $coachId, array $bookings): array
    {
        if ($coachId <= 0) {
            return ['status' => false, 'message' => '請選擇教練'];
        }

        DB::transaction(function () use ($coachId, $bookings) {
            EduPrivate::where('coach_id', $coachId)->delete();

            foreach ($bookings as $booking) {
                EduPrivate::create([
                    'coach_id' => $coachId,
                    'enrollment_id' => $booking['enrollment_id'] ?? null,
                    'student_name' => $booking['student_name'] ?? '',
                    'student_phone' => $booking['student_phone'] ?? '',
                    'district' => $booking['district'] ?? '',
                    'pool' => $booking['pool'] ?? '',
                    'other_location' => $booking['other_location'] ?? '',
                    'class_date' => $booking['class_date'] ?? null,
                    'class_time' => $booking['class_time'] ?? '',
                    'class_end_time' => $booking['class_end_time'] ?? '',
                    'ratio' => $booking['ratio'] ?? '',
                    'type' => $booking['type'] ?? '',
                    'fee' => $booking['fee'] ?? 0,
                    'status' => $booking['status'] ?? 'Pending',
                    'payment_date' => $booking['payment_date'] ?? null,
                    'refund_date' => $booking['refund_date'] ?? null,
                    'attendance' => $booking['attendance'] ?? '',
                    'cumulative_override' => $booking['cumulative_override'] ?? null,
                    'remark' => $booking['remark'] ?? '',
                ]);
            }
        });

        return ['status' => true, 'message' => 'Success'];
    }
}
